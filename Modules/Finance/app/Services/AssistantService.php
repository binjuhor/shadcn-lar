<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Contracts\AdvisorRuntime;
use Modules\Finance\Exceptions\SmartInputException;
use Modules\Finance\Models\{AdvisorConversation, AdvisorMessage, SmartInputHistory, Transaction};
use Modules\Settings\Services\AiCredentialResolver;

/**
 * Unified AI chat: transaction tracking (text, voice, receipt) and financial advice in one
 * conversation. Attachments and numeric-only input use the Smart Input parsers (no agent
 * call); free text goes to the AI agent, which can answer or return transaction drafts.
 */
class AssistantService
{
    private const HISTORY_LIMIT = 20;

    public function __construct(
        protected SmartInputParsingService $parsing,
        protected SmartInputTransactionService $transactions,
        protected FinancialAdvisorService $advisor,
        protected AiCredentialResolver $credentials,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('aiadvisor.enabled', false) && app()->bound(AdvisorRuntime::class);
    }

    public function isConfigured(User $user): bool
    {
        $provider = $this->credentials->provider($user);

        if ($provider === 'ollama') {
            return $this->credentials->ollamaBaseUrl($user) !== '';
        }

        return $this->credentials->apiKey($provider, $user) !== null;
    }

    /**
     * Whether a voice note can be parsed for this user (drives the mic button).
     */
    public function supportsVoice(User $user): bool
    {
        return $this->parsing->voiceParser($user) !== null;
    }

    /**
     * The user's conversation by id, or a new one when no id is given.
     */
    public function conversationFor(User $user, ?int $conversationId): AdvisorConversation
    {
        return $conversationId
            ? AdvisorConversation::forUser($user->id)->findOrFail($conversationId)
            : AdvisorConversation::create(['user_id' => $user->id]);
    }

    /**
     * An assistant-chat message owned by the user (404 otherwise).
     */
    public function messageFor(User $user, int $messageId): AdvisorMessage
    {
        return AdvisorMessage::whereKey($messageId)
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();
    }

    /**
     * Handle one user turn. Returns the stored user message (content may now be the voice
     * transcript) and the assistant reply, so callers never have to re-query for them.
     *
     * @return array{user: AdvisorMessage, reply: AdvisorMessage}
     */
    public function send(
        User $user,
        AdvisorConversation $conversation,
        ?string $text,
        ?UploadedFile $image = null,
        ?UploadedFile $audio = null,
        string $language = 'vi',
    ): array {
        $text = $text !== null ? trim($text) : null;
        $inputType = match (true) {
            $audio !== null => 'voice',
            $image !== null => $text ? 'text_image' : 'image',
            default => 'text',
        };

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $text ?: $this->placeholder($inputType, $language),
            'meta' => ['input_type' => $inputType],
        ]);

        $this->refreshTitle($conversation, $text ?: $this->placeholder($inputType, $language));

        $reply = $inputType !== 'text' || $this->parsing->isNumericOnly((string) $text)
            ? $this->replyFromParser($user, $conversation, $userMessage, $text, $image, $audio, $language)
            : $this->replyFromAgent($user, $conversation, (string) $text);

        return ['user' => $userMessage, 'reply' => $reply];
    }

    /**
     * Save one draft from an assistant message as a real transaction.
     *
     * @param  array<string, mixed>  $data  validated against SmartInputTransactionService::rules()
     *
     * @throws SmartInputException
     */
    public function saveDraft(User $user, AdvisorMessage $message, int $index, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $message, $index, $data) {
            $message = AdvisorMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            $meta = $message->meta ?? [];
            $draft = $meta['drafts'][$index] ?? null;

            if ($message->role !== 'assistant' || ! is_array($draft)) {
                throw new SmartInputException('Draft not found.');
            }

            if (! empty($draft['saved_transaction_id'])) {
                throw new SmartInputException('This draft was already saved.');
            }

            // The history link always comes from the stored draft, never from the client.
            // Drop it if the user deleted that history entry, so the draft stays saveable.
            $historyId = $draft['history_id'] ?? null;
            $data['history_id'] = $historyId && SmartInputHistory::forUser($user->id)->whereKey($historyId)->exists()
                ? $historyId
                : null;
            $transaction = $this->transactions->save($user, $data);

            $meta['drafts'][$index] = [
                ...$draft,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'],
                'saved_transaction_id' => $transaction->id,
                'saved_currency_code' => $transaction->currency_code,
            ];
            $message->update(['meta' => $meta]);

            return $transaction;
        });
    }

    /**
     * Apply a pending write-tool confirmation and mark the originating action as resolved.
     */
    public function confirm(User $user, AdvisorConversation $conversation, string $token): AdvisorMessage
    {
        $response = app(AdvisorRuntime::class)->confirm($user, $conversation->id, $token);

        $source = $conversation->messages()
            ->where('role', 'assistant')
            ->whereNotNull('meta')
            ->reorder()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->first(fn (AdvisorMessage $m) => collect($m->meta['actions'] ?? [])->contains('token', $token));

        if ($source) {
            $meta = $source->meta;
            $meta['actions'] = collect($meta['actions'])
                ->map(fn (array $action) => ($action['token'] ?? null) === $token ? [...$action, 'status' => 'resolved'] : $action)
                ->all();
            $source->update(['meta' => $meta]);
        }

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response['content'],
            'ai_provider' => $this->credentials->provider($user),
        ]);
    }

    /**
     * Client payload for a message. Confirmation tokens stay visible only while pending.
     *
     * @return array<string, mixed>
     */
    public function messagePayload(AdvisorMessage $message): array
    {
        $meta = $message->meta;

        if ($meta && isset($meta['actions'])) {
            $meta['actions'] = collect($meta['actions'])
                ->map(fn (array $action) => ($action['status'] ?? 'pending') === 'pending' ? $action : collect($action)->except('token')->all())
                ->all();
        }

        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'meta' => $meta,
            'created_at' => $message->created_at->toISOString(),
        ];
    }

    protected function replyFromParser(
        User $user,
        AdvisorConversation $conversation,
        AdvisorMessage $userMessage,
        ?string $text,
        ?UploadedFile $image,
        ?UploadedFile $audio,
        string $language,
    ): AdvisorMessage {
        try {
            $parsed = match (true) {
                $audio !== null => $this->parsing->parseVoice($user, $audio, $language),
                $image !== null => $this->parsing->parseReceipt($user, $image, $language, $text),
                default => $this->parsing->parseText($user, (string) $text, $language),
            };
        } catch (SmartInputException $e) {
            return $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $e->getMessage(),
                'meta' => ['error' => true],
            ]);
        }

        $drafts = array_map(fn (array $draft) => [...$draft, 'history_id' => $parsed['history_id']], $parsed['drafts']);

        // Show the attachment and voice transcript on the user's bubble
        $history = SmartInputHistory::find($parsed['history_id']);
        $userMeta = [
            ...$userMessage->meta,
            'history_id' => $parsed['history_id'],
            'media_url' => $history?->getFirstMediaUrl('input_attachments') ?: null,
        ];
        $userUpdate = ['meta' => $userMeta];
        if ($audio !== null && ! empty($drafts[0]['raw_text'])) {
            $userUpdate['content'] = $drafts[0]['raw_text'];
            $this->refreshTitle($conversation, $drafts[0]['raw_text']);
        }
        $userMessage->update($userUpdate);

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $this->draftSummary(count($drafts), $language),
            'meta' => ['drafts' => $drafts],
            'ai_provider' => $history?->ai_provider,
        ]);
    }

    protected function replyFromAgent(User $user, AdvisorConversation $conversation, string $text): AdvisorMessage
    {
        $response = app(AdvisorRuntime::class)->assist($user, $conversation->id, $this->history($conversation));

        $drafts = $response['drafts'] ?? [];
        $actions = collect($response['actions'] ?? [])->map(fn (array $a) => [...$a, 'status' => 'pending'])->all();
        $content = $response['content'];

        if ($actions !== []) {
            // The confirm button replaces the typed `/confirm <token>` instruction
            $content = __('Please review the proposed change and confirm to apply it.');
        } elseif ($drafts === []) {
            $content = $this->advisor->appendChartBlocks($content, $text, $user->id);
        } elseif (trim($content) === '') {
            $content = $this->draftSummary(count($drafts), $user->language ?: 'vi');
        }

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'meta' => array_filter(['drafts' => $drafts, 'actions' => $actions]) ?: null,
            'ai_provider' => $response['provider'] ?? null,
            'prompt_tokens' => $response['prompt_tokens'] ?? null,
            'completion_tokens' => $response['completion_tokens'] ?? null,
        ]);
    }

    /**
     * Latest messages in chronological order. Draft cards are summarized so the model
     * knows what was already drafted or saved.
     *
     * @return list<array{role: string, content: string}>
     */
    protected function history(AdvisorConversation $conversation): array
    {
        return $conversation->messages()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->values()
            ->map(function (AdvisorMessage $m) {
                $content = $m->content;
                $drafts = $m->meta['drafts'] ?? [];

                if ($drafts !== []) {
                    $content .= "\n[Drafts: ".collect($drafts)->map(fn (array $d) => sprintf(
                        '%s %s %s (%s)',
                        $d['type'] ?? 'expense',
                        $d['amount'] ?? 0,
                        $d['description'] ?? '',
                        empty($d['saved_transaction_id']) ? 'not saved' : 'saved',
                    ))->implode('; ').']';
                }

                return ['role' => $m->role, 'content' => $content];
            })
            ->all();
    }

    protected function draftSummary(int $count, string $language): string
    {
        if ($language === 'en') {
            return $count === 1
                ? 'I found 1 transaction. Review it and tap Save.'
                : "I found {$count} transactions. Review them and tap Save.";
        }

        return "Mình tìm thấy {$count} giao dịch. Kiểm tra lại rồi bấm Lưu nhé.";
    }

    /**
     * Title a conversation from its first real text. A placeholder title ("Ghi âm",
     * "Receipt photo", ...) stays only until a transcript or typed message replaces it.
     */
    protected function refreshTitle(AdvisorConversation $conversation, string $candidate): void
    {
        if ($candidate === '') {
            return;
        }

        $placeholders = $this->placeholders();

        // Keep a real title; never swap one placeholder for another
        if ($conversation->title !== null
            && (! in_array($conversation->title, $placeholders, true) || in_array($candidate, $placeholders, true))) {
            return;
        }

        $conversation->update(['title' => Str::limit($candidate, 60)]);
    }

    /**
     * @return list<string>
     */
    protected function placeholders(): array
    {
        return collect(['voice', 'image'])
            ->flatMap(fn (string $type) => [$this->placeholder($type, 'vi'), $this->placeholder($type, 'en')])
            ->all();
    }

    protected function placeholder(string $inputType, string $language): string
    {
        $en = $language === 'en';

        return match ($inputType) {
            'voice' => $en ? 'Voice note' : 'Ghi âm',
            'image' => $en ? 'Receipt photo' : 'Ảnh hóa đơn',
            default => '',
        };
    }
}
