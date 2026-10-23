<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Str;
use Inertia\{Inertia, Response};
use Modules\Finance\Exceptions\SmartInputException;
use Modules\Finance\Http\Requests\Assistant\SendAssistantMessageRequest;
use Modules\Finance\Models\{Account, AdvisorConversation, AdvisorMessage, Category};
use Modules\Finance\Services\{AssistantService, SmartInputTransactionService};

/**
 * Unified AI chat page: Smart Input tracking + AI Advisor in one conversation.
 */
class AssistantController extends Controller
{
    public function __construct(protected AssistantService $assistant) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = AdvisorConversation::forUser($user->id)
            ->with('latestMessage')
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (AdvisorConversation $c) => [
                'id' => $c->id,
                'title' => $c->title ?? 'New conversation',
                'updated_at' => $c->updated_at->toISOString(),
                'preview' => $c->latestMessage?->content ? Str::limit($c->latestMessage->content, 80) : null,
            ]);

        $activeConversationId = $request->integer('conversation') ?: null;
        $messages = [];

        if ($activeConversationId) {
            $conversation = AdvisorConversation::forUser($user->id)->find($activeConversationId);

            if ($conversation) {
                $messages = $conversation->messages()
                    ->orderBy('id')
                    ->get()
                    ->map(fn (AdvisorMessage $m) => $this->assistant->messagePayload($m))
                    ->all();
            } else {
                $activeConversationId = null;
            }
        }

        return Inertia::render('Finance::assistant/index', [
            'conversations' => $conversations,
            'activeConversationId' => $activeConversationId,
            'messages' => $messages,
            'accounts' => Account::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN currency_code = 'VND' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'account_type', 'currency_code']),
            'categories' => Category::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'icon', 'color']),
            'aiConfigured' => $this->assistant->isConfigured($user),
            'voiceEnabled' => $this->assistant->supportsVoice($user),
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
            'conversation_id' => $conversation->id,
            'conversation_title' => $conversation->fresh()->title,
            'user_message' => $this->assistant->messagePayload($userMessage),
            'message' => $this->assistant->messagePayload($reply),
        ]);
    }

    public function saveDraft(Request $request, int $message, int $index): JsonResponse
    {
        $user = $request->user();
        $advisorMessage = $this->assistant->messageFor($user, $message);
        $validated = $request->validate(SmartInputTransactionService::rules());

        try {
            $this->assistant->saveDraft($user, $advisorMessage, $index, $validated);
        } catch (SmartInputException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $this->assistant->messagePayload($advisorMessage->fresh()),
        ]);
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
            'message' => $this->assistant->messagePayload($reply),
        ]);
    }

    public function renameConversation(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $conversation = AdvisorConversation::forUser($request->user()->id)->findOrFail($conversationId);
        $conversation->update(['title' => trim($validated['title'])]);

        return response()->json([
            'success' => true,
            'conversation' => ['id' => $conversation->id, 'title' => $conversation->title],
        ]);
    }

    public function destroyConversation(Request $request, int $conversationId): JsonResponse
    {
        AdvisorConversation::forUser($request->user()->id)->findOrFail($conversationId)->delete();

        return response()->json(['success' => true]);
    }
}
