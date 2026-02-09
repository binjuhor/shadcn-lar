<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\{Inertia, Response};
use Modules\Finance\Models\AdvisorConversation;
use Modules\Finance\Services\FinancialAdvisorService;

class FinancialAdvisorController extends Controller
{
    public function __construct(
        protected FinancialAdvisorService $advisorService
    ) {}

    public function index(Request $request): Response
    {
        $userId = auth()->id();

        $conversations = AdvisorConversation::forUser($userId)
            ->with('latestMessage')
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (AdvisorConversation $c) => [
                'id' => $c->id,
                'title' => $c->title ?? 'New conversation',
                'updated_at' => $c->updated_at->toISOString(),
                'preview' => $c->latestMessage?->content
                    ? \Illuminate\Support\Str::limit($c->latestMessage->content, 80)
                    : null,
            ]);

        $activeConversationId = $request->integer('conversation');
        $messages = [];

        if ($activeConversationId) {
            $conversation = AdvisorConversation::forUser($userId)
                ->find($activeConversationId);

            if ($conversation) {
                $messages = $conversation->messages()
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'role' => $m->role,
                        'content' => $m->content,
                        'created_at' => $m->created_at->toISOString(),
                    ])
                    ->toArray();
            } else {
                $activeConversationId = null;
            }
        }

        return Inertia::render('Finance::advisor/index', [
            'conversations' => $conversations,
            'activeConversationId' => $activeConversationId,
            'messages' => $messages,
            'aiConfigured' => $this->advisorService->isConfigured(),
        ]);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $userId = auth()->id();
        $conversationId = $validated['conversation_id'] ?? null;

        if ($conversationId) {
            $conversation = AdvisorConversation::forUser($userId)
                ->findOrFail($conversationId);
        } else {
            $conversation = AdvisorConversation::create([
                'user_id' => $userId,
            ]);
        }

        try {
            $assistantMessage = $this->advisorService->sendMessage(
                $conversation,
                $validated['message'],
                $userId
            );

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->id,
                'conversation_title' => $conversation->fresh()->title,
                'message' => [
                    'id' => $assistantMessage->id,
                    'role' => $assistantMessage->role,
                    'content' => $assistantMessage->content,
                    'created_at' => $assistantMessage->created_at->toISOString(),
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            $error = app()->isLocal()
                ? $e->getMessage()
                : 'Failed to get AI response. Please try again.';

            return response()->json([
                'success' => false,
                'error' => $error,
            ], 500);
        }
    }

    public function destroyConversation(int $conversationId)
    {
        $conversation = AdvisorConversation::forUser(auth()->id())
            ->findOrFail($conversationId);

        $conversation->delete();

        return response()->json(['success' => true]);
    }
}
