<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Str;
use Modules\Finance\Exceptions\SmartInputException;
use Modules\Finance\Http\Requests\Assistant\SendAssistantMessageRequest;
use Modules\Finance\Models\{AdvisorConversation, AdvisorMessage};
use Modules\Finance\Services\{AssistantService, SmartInputTransactionService};

/**
 * Mobile API for the unified AI chat (Smart Input tracking + AI Advisor).
 */
class AssistantApiController extends Controller
{
    public function __construct(protected AssistantService $assistant) {}

    public function conversations(Request $request): JsonResponse
    {
        $conversations = AdvisorConversation::forUser($request->user()->id)
            ->with('latestMessage')
            ->orderByDesc('updated_at')
            ->paginate(max(1, min($request->integer('per_page', 20) ?: 20, 50)));

        return response()->json([
            'success' => true,
            'data' => collect($conversations->items())->map(fn (AdvisorConversation $c) => $this->conversationPayload($c)),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Messages newest-first page, returned in chronological order; pass before_id to load older.
     */
    public function messages(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $model = AdvisorConversation::forUser($request->user()->id)->findOrFail($conversation);
        $limit = (int) ($validated['limit'] ?? 50);

        $items = $model->messages()
            ->reorder()
            ->when($validated['before_id'] ?? null, fn ($q, $beforeId) => $q->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->take($limit + 1)
            ->get();

        $hasMore = $items->count() > $limit;
        $items = $items->take($limit)->reverse()->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items->map(fn (AdvisorMessage $m) => $this->assistant->messagePayload($m)),
                'has_more' => $hasMore,
                'next_before_id' => $hasMore ? $items->first()?->id : null,
            ],
        ]);
    }

    public function send(SendAssistantMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->assistant->conversationFor($user, $request->integer('conversation_id') ?: null);

        try {
            ['user' => $userMessage, 'reply' => $reply] = $this->assistant->send(
                $user,
                $conversation,
                $request->input('message'),
                $request->file('image'),
                $request->file('audio'),
                $request->input('language', $user->language === 'en' ? 'en' : 'vi'),
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'conversation_id' => $conversation->id,
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to get AI response. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $this->conversationPayload($conversation->fresh()),
                'user_message' => $this->assistant->messagePayload($userMessage),
                'message' => $this->assistant->messagePayload($reply),
            ],
        ]);
    }

    public function saveDraft(Request $request, int $message, int $index): JsonResponse
    {
        $user = $request->user();
        $advisorMessage = $this->assistant->messageFor($user, $message);
        $validated = $request->validate(SmartInputTransactionService::rules());

        try {
            $transaction = $this->assistant->saveDraft($user, $advisorMessage, $index, $validated);
        } catch (SmartInputException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $transaction->id,
                'message' => $this->assistant->messagePayload($advisorMessage->fresh()),
            ],
        ], 201);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{48}$/'],
        ]);

        $conversation = AdvisorConversation::forUser($request->user()->id)->findOrFail($validated['conversation_id']);
        $reply = $this->assistant->confirm($request->user(), $conversation, $validated['token']);

        return response()->json([
            'success' => true,
            'data' => ['message' => $this->assistant->messagePayload($reply)],
        ]);
    }

    public function rename(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $model = AdvisorConversation::forUser($request->user()->id)->findOrFail($conversation);
        $model->update(['title' => trim($validated['title'])]);

        return response()->json(['success' => true, 'data' => $this->conversationPayload($model)]);
    }

    public function destroy(Request $request, int $conversation): JsonResponse
    {
        AdvisorConversation::forUser($request->user()->id)->findOrFail($conversation)->delete();

        return response()->json(['success' => true]);
    }

    /** @return array<string, mixed> */
    protected function conversationPayload(AdvisorConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title ?? 'New conversation',
            'updated_at' => $conversation->updated_at->toISOString(),
            'preview' => $conversation->latestMessage?->content ? Str::limit($conversation->latestMessage->content, 80) : null,
        ];
    }
}
