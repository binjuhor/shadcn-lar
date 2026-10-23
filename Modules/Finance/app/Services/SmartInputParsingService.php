<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Finance\Contracts\TransactionParserInterface;
use Modules\Finance\Exceptions\SmartInputException;
use Modules\Finance\Models\SmartInputHistory;
use Modules\Settings\Services\AiCredentialResolver;

/**
 * Turns text, voice, and receipt input into editable transaction drafts and records
 * a SmartInputHistory row for each parse. Shared by the Smart Input web/API controllers
 * and the AI assistant chat.
 */
class SmartInputParsingService
{
    /** Providers whose parser cannot read audio; voice always goes through Gemini instead */
    public const NO_VOICE_PROVIDERS = ['claude', 'deepseek', 'ollama', 'openrouter', 'openai'];

    /** Providers without native vision support */
    public const NO_VISION_PROVIDERS = ['deepseek'];

    private const NUMERIC_MULTIPLIERS = [
        'k' => 1000,
        'nghìn' => 1000,
        'nghin' => 1000,
        'tr' => 1000000,
        'triệu' => 1000000,
        'trieu' => 1000000,
        'tỷ' => 1000000000,
        'ty' => 1000000000,
    ];

    public function __construct(
        protected AccountDetectionService $accountDetection,
        protected BillAttachmentService $billAttachmentService,
        protected AiCredentialResolver $credentials,
    ) {}

    /**
     * @return array{drafts: list<array<string, mixed>>, history_id: int, multiple: bool}
     */
    public function parseText(User $user, string $text, string $language = 'vi'): array
    {
        $text = trim($text);

        if ($this->isNumericOnly($text)) {
            return $this->parseNumeric($user, $text, $language);
        }

        $parser = $this->parser($user);
        $result = $parser->parseText($text, $language);
        $this->assertParsed($result, 'Failed to parse text');

        $history = $this->recordHistory($user, 'text', $text, $result, $this->providerName($parser), $language);

        return $this->single($this->enrich($user->id, $result, $parser), $history->id);
    }

    /**
     * @return array{drafts: list<array<string, mixed>>, history_id: int, multiple: bool}
     */
    public function parseVoice(User $user, UploadedFile $audio, string $language = 'vi'): array
    {
        $parser = $this->voiceParser($user);

        if (! $parser) {
            $provider = $this->credentials->provider($user);

            throw new SmartInputException("Voice input is not supported by {$provider}. Please configure a Gemini API key to enable voice parsing.");
        }

        $result = $parser->parseVoice($audio, $language);
        $this->assertParsed($result, 'Failed to parse audio');

        $history = $this->recordHistory($user, 'voice', $result['raw_text'] ?? null, $result, $this->providerName($parser), $language, $audio);

        return $this->single($this->enrich($user->id, $result, $parser), $history->id);
    }

    /**
     * Parse a receipt image. When $notes is given the input is stored as text_image
     * and the text becomes the transaction note.
     *
     * @return array{drafts: list<array<string, mixed>>, history_id: int, multiple: bool}
     */
    public function parseReceipt(User $user, UploadedFile $image, string $language = 'vi', ?string $notes = null): array
    {
        $parser = $this->receiptParser($user);

        if (! $parser) {
            $provider = $this->credentials->provider($user);

            throw new SmartInputException("Image parsing is not supported by {$provider}. Switch to a provider with vision support.");
        }

        $result = $parser->parseReceipt($image, $language);
        $this->assertParsed($result, 'Failed to parse receipt');

        $notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;
        $history = $this->recordHistory(
            $user,
            $notes ? 'text_image' : 'image',
            $notes,
            $result,
            $this->providerName($parser),
            $language,
            $image,
        );

        return $this->single($this->enrich($user->id, $result, $parser, $notes), $history->id);
    }

    /**
     * Build drafts from transactions already extracted by an AI agent (no parser call).
     * Each draft gets its own history row so saving links the history like Smart Input does.
     *
     * @param  array<int, array<string, mixed>>  $results  items with type/amount/description/hints
     * @return list<array<string, mixed>>
     */
    public function draftsFromResults(User $user, array $results, ?string $aiProvider, string $language = 'vi'): array
    {
        return collect($results)
            ->map(function (array $result) use ($user, $aiProvider, $language) {
                $result['raw_text'] ??= $result['description'] ?? null;
                $history = $this->recordHistory($user, 'text', $result['raw_text'], $result, $aiProvider, $language);

                return [...$this->enrich($user->id, $result), 'history_id' => $history->id];
            })
            ->values()
            ->all();
    }

    /**
     * Resolve category/account suggestions for a parsed result.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function enrich(int $userId, array $result, ?TransactionParserInterface $parser = null, ?string $notes = null): array
    {
        $type = $result['type'] ?? 'expense';
        $matcher = $parser ?? $this->matcher();

        $suggestedCategory = ! empty($result['category_hint'])
            ? $matcher->matchCategory($result['category_hint'], $userId, $type)
            : null;

        $suggestedAccount = $this->accountDetection->detect(
            $userId,
            $result['raw_text'] ?? null,
            $result['account_hint'] ?? null,
            $result['description'] ?? null,
        );

        $suggestedTransferAccount = ! empty($result['transfer_account_hint']) && $type === 'transfer'
            ? $matcher->matchAccount($result['transfer_account_hint'], $userId)
            : null;

        return [
            'type' => $type,
            'amount' => $result['amount'] ?? 0,
            'description' => $result['description'] ?? '',
            'suggested_category' => $suggestedCategory,
            'suggested_account' => $suggestedAccount,
            'suggested_transfer_account' => $suggestedTransferAccount,
            'transaction_date' => $result['transaction_date'] ?? now()->format('Y-m-d'),
            'confidence' => $result['confidence'] ?? 0.8,
            'raw_text' => $result['raw_text'] ?? null,
            'notes' => $notes,
        ];
    }

    /**
     * Legacy Smart Input response shape: single `data` or `multiple` + `items`.
     *
     * @param  array{drafts: list<array<string, mixed>>, history_id: int, multiple: bool}  $parsed
     * @return array<string, mixed>
     */
    public function payload(array $parsed): array
    {
        if ($parsed['multiple']) {
            return ['success' => true, 'multiple' => true, 'items' => $parsed['drafts'], 'history_id' => $parsed['history_id']];
        }

        return ['success' => true, 'data' => $parsed['drafts'][0], 'history_id' => $parsed['history_id']];
    }

    /**
     * True when the input is only numbers (e.g. "50000", "210.55", "50k 30k"), which is
     * handled locally without any AI call.
     */
    public function isNumericOnly(string $text): bool
    {
        return $this->isMultipleNumericInput($text) || $this->isSimpleNumericInput($text);
    }

    public function parser(?User $user = null): TransactionParserInterface
    {
        return TransactionParserFactory::make($this->credentials->provider($user));
    }

    /**
     * Voice falls back to Gemini for providers without native audio support.
     */
    public function voiceParser(?User $user = null): ?TransactionParserInterface
    {
        $provider = $this->credentials->provider($user);

        if (! in_array($provider, self::NO_VOICE_PROVIDERS, true)) {
            return TransactionParserFactory::make($provider);
        }

        return $this->credentials->apiKey('gemini', $user) ? TransactionParserFactory::make('gemini') : null;
    }

    /**
     * Images fall back to Claude for providers without vision support.
     */
    public function receiptParser(?User $user = null): ?TransactionParserInterface
    {
        $provider = $this->credentials->provider($user);

        if (! in_array($provider, self::NO_VISION_PROVIDERS, true)) {
            return TransactionParserFactory::make($provider);
        }

        return $this->credentials->apiKey('claude', $user) ? TransactionParserFactory::make('claude') : null;
    }

    /**
     * @return array{drafts: list<array<string, mixed>>, history_id: int, multiple: bool}
     */
    protected function parseNumeric(User $user, string $text, string $language): array
    {
        // Multiple values first: the simple check strips spaces, so "50000 30000" would merge
        if (! $this->isMultipleNumericInput($text)) {
            $result = [
                'type' => 'expense',
                'amount' => (float) str_replace([',', ' '], '', $text),
                'description' => '',
                'confidence' => 0.5,
                'raw_text' => $text,
            ];
            $history = $this->recordHistory($user, 'text', $text, $result, null, $language);

            return $this->single($this->enrich($user->id, $result), $history->id);
        }

        $suggestedAccount = $this->accountDetection->detect($user->id, $text);
        $drafts = collect(preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $token) => [
                'type' => 'expense',
                'amount' => $this->parseNumericToken($token),
                'description' => '',
                'suggested_category' => null,
                'suggested_account' => $suggestedAccount,
                'suggested_transfer_account' => null,
                'transaction_date' => now()->format('Y-m-d'),
                'confidence' => 0.5,
                'raw_text' => $token,
                'notes' => null,
            ])
            ->values()
            ->all();

        $history = $this->recordHistory($user, 'text', $text, [
            'type' => 'expense',
            'amount' => array_sum(array_column($drafts, 'amount')),
            'description' => 'Multiple transactions',
            'confidence' => 0.5,
            'raw_text' => $text,
        ], null, $language);

        return ['drafts' => $drafts, 'history_id' => $history->id, 'multiple' => true];
    }

    protected function isSimpleNumericInput(string $text): bool
    {
        return preg_match('/^\d+(\.\d+)?$/', str_replace([',', ' '], '', $text)) === 1;
    }

    /**
     * At least two numeric tokens, each optionally suffixed with k/tr/tỷ.
     */
    protected function isMultipleNumericInput(string $text): bool
    {
        $tokens = preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (count($tokens) < 2) {
            return false;
        }

        foreach ($tokens as $token) {
            $normalized = str_replace([',', '.'], '', mb_strtolower(trim($token)));

            if (! preg_match('/^\d+(k|nghìn|nghin|tr|triệu|trieu|tỷ|ty)?$/u', $normalized)) {
                return false;
            }
        }

        return true;
    }

    protected function parseNumericToken(string $token): float
    {
        $normalized = str_replace([',', ' '], '', mb_strtolower(trim($token)));

        foreach (self::NUMERIC_MULTIPLIERS as $suffix => $multiplier) {
            if (preg_match('/^(\d+)'.preg_quote($suffix, '/').'$/u', $normalized, $matches)) {
                return (float) $matches[1] * $multiplier;
            }
        }

        // Dots are thousand separators in Vietnamese style (50.000)
        return (float) str_replace('.', '', $normalized);
    }

    /**
     * Category/account matching is DB-only, so any parser instance works without an API call.
     */
    protected function matcher(): TransactionParserInterface
    {
        return new DeepSeekTransactionParser;
    }

    /** @param array<string, mixed> $result */
    protected function assertParsed(array $result, string $fallbackError): void
    {
        if (! ($result['success'] ?? false)) {
            throw new SmartInputException($result['error'] ?? $fallbackError);
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array{drafts: list<array<string, mixed>>, history_id: int, multiple: bool}
     */
    protected function single(array $draft, int $historyId): array
    {
        return ['drafts' => [$draft], 'history_id' => $historyId, 'multiple' => false];
    }

    /** @param array<string, mixed> $parsedResult */
    protected function recordHistory(
        User $user,
        string $inputType,
        ?string $rawText,
        array $parsedResult,
        ?string $aiProvider,
        string $language,
        ?UploadedFile $attachment = null,
    ): SmartInputHistory {
        $history = SmartInputHistory::create([
            'user_id' => $user->id,
            'input_type' => $inputType,
            'raw_text' => $rawText,
            'parsed_result' => $parsedResult,
            'ai_provider' => $aiProvider,
            'language' => $language,
            'confidence' => $parsedResult['confidence'] ?? null,
        ]);

        if ($attachment) {
            $this->billAttachmentService->attach($history, 'input_attachments', $attachment);
        }

        return $history;
    }

    protected function providerName(TransactionParserInterface $parser): string
    {
        return match (true) {
            $parser instanceof ClaudeTransactionParser => 'claude',
            $parser instanceof GeminiTransactionParser => 'gemini',
            $parser instanceof OllamaTransactionParser => 'ollama',
            $parser instanceof DeepSeekTransactionParser => 'deepseek',
            $parser instanceof OpenAiTransactionParser => 'openai',
            $parser instanceof OpenRouterTransactionParser => 'openrouter',
            default => 'unknown',
        };
    }
}
