<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Finance\Contracts\AdvisorRuntime;
use Modules\Finance\Models\{Account, AdvisorConversation, AdvisorMessage, Currency, SmartInputHistory, Transaction};
use Modules\Finance\Services\{AssistantService, GeminiTransactionParser, SmartInputParsingService};
use RuntimeException;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    /** Fake agent runtime: records calls and returns the queued assist() response */
    private object $runtime;

    protected function setUp(): void
    {
        parent::setUp();

        config(['aiadvisor.enabled' => true]);

        Currency::create(['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'decimal_places' => 0, 'is_default' => true]);

        $this->user = User::factory()->create();
        $this->account = $this->createAccount($this->user);

        $this->runtime = new class implements AdvisorRuntime
        {
            public array $assistCalls = [];

            public array $confirmCalls = [];

            public array $next = ['content' => 'Hello', 'prompt_tokens' => null, 'completion_tokens' => null, 'drafts' => [], 'actions' => [], 'provider' => 'gemini'];

            public function run(User $actor, int $conversationId, array $history): array
            {
                throw new RuntimeException('Advisor run() must not be used by the assistant.');
            }

            public function assist(User $actor, int $conversationId, array $history): array
            {
                $this->assistCalls[] = $history;

                return $this->next;
            }

            public function confirm(User $actor, int $conversationId, string $token): array
            {
                $this->confirmCalls[] = $token;

                return ['content' => 'The financial change was completed successfully.', 'prompt_tokens' => null, 'completion_tokens' => null];
            }
        };
        $this->app->instance(AdvisorRuntime::class, $this->runtime);
    }

    public function test_assistant_is_hidden_when_agent_is_disabled(): void
    {
        config(['aiadvisor.enabled' => false]);

        $this->actingAs($this->user)->get(route('dashboard.finance.assistant.index'))->assertNotFound();
        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.send'), ['message' => 'hi'])->assertNotFound();
        $this->actingAs($this->user)->postJson('/api/v1/finance/assistant/send', ['message' => 'hi'])->assertNotFound();
    }

    public function test_page_renders_conversation_with_message_meta(): void
    {
        $message = $this->assistantMessage([$this->draft()]);

        $this->actingAs($this->user)
            ->get(route('dashboard.finance.assistant.index', ['conversation' => $message->conversation_id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance::assistant/index', false)
                ->where('activeConversationId', $message->conversation_id)
                ->has('messages', 1)
                ->where('messages.0.meta.drafts.0.amount', 50000)
                ->has('accounts', 1)
                ->has('aiConfigured'));
    }

    public function test_text_message_goes_to_agent_and_stores_drafts(): void
    {
        $this->runtime->next['content'] = 'Please review the card.';
        $this->runtime->next['drafts'] = [$this->draft(['history_id' => $this->history()->id])];

        $response = $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.send'), [
            'message' => 'cafe 50k hôm qua',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message.content', 'Please review the card.')
            ->assertJsonPath('message.meta.drafts.0.amount', 50000)
            ->assertJsonPath('user_message.content', 'cafe 50k hôm qua');

        $this->assertCount(1, $this->runtime->assistCalls);
        $this->assertSame('cafe 50k hôm qua', last($this->runtime->assistCalls[0])['content']);
        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_numeric_only_input_is_parsed_locally_without_agent(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.send'), [
            'message' => '50k 30k',
            'language' => 'en',
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'message.meta.drafts')
            ->assertJsonPath('message.meta.drafts.0.amount', 50000)
            ->assertJsonPath('message.meta.drafts.1.amount', 30000);

        $this->assertSame([], $this->runtime->assistCalls);
        $this->assertNotNull($response->json('message.meta.drafts.0.history_id'));
    }

    public function test_space_separated_plain_numbers_become_separate_drafts(): void
    {
        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.send'), ['message' => '50000 30000'])
            ->assertOk()
            ->assertJsonCount(2, 'message.meta.drafts')
            ->assertJsonPath('message.meta.drafts.0.amount', 50000)
            ->assertJsonPath('message.meta.drafts.1.amount', 30000);

        $this->actingAs($this->user)->postJson('/api/v1/finance/smart-input/parse-text', ['text' => '50000 30000'])
            ->assertOk()
            ->assertJsonPath('multiple', true)
            ->assertJsonCount(2, 'items');
    }

    public function test_draft_is_still_saveable_after_its_history_entry_is_deleted(): void
    {
        $history = $this->history();
        $message = $this->assistantMessage([$this->draft(['history_id' => $history->id])]);
        $history->delete();

        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.drafts.save', [$message->id, 0]), $this->savePayload())
            ->assertOk();

        $this->assertDatabaseCount('finance_transactions', 1);
    }

    public function test_saving_a_draft_creates_transaction_and_links_history(): void
    {
        $history = $this->history();
        $message = $this->assistantMessage([$this->draft(['history_id' => $history->id])]);

        $response = $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.drafts.save', [$message->id, 0]), [
            'type' => 'expense',
            'amount' => 45000,
            'description' => 'Coffee (edited)',
            'account_id' => $this->account->id,
            'transaction_date' => '2026-10-08',
            // A client-supplied history id is ignored in favour of the stored draft's
            'history_id' => 999999,
        ]);

        $response->assertOk()
            ->assertJsonPath('message.meta.drafts.0.description', 'Coffee (edited)')
            ->assertJsonPath('message.meta.drafts.0.saved_currency_code', 'VND');

        $transaction = Transaction::sole();
        $this->assertSame($transaction->id, $response->json('message.meta.drafts.0.saved_transaction_id'));
        $this->assertSame($transaction->id, $history->fresh()->transaction_id);
        $this->assertTrue($history->fresh()->transaction_saved);
    }

    public function test_a_draft_cannot_be_saved_twice(): void
    {
        $message = $this->assistantMessage([$this->draft(['saved_transaction_id' => 1])]);

        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.drafts.save', [$message->id, 0]), $this->savePayload())
            ->assertUnprocessable()
            ->assertJsonPath('error', 'This draft was already saved.');

        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_user_cannot_save_another_users_draft_or_use_their_account(): void
    {
        $other = User::factory()->create();
        $otherMessage = $this->assistantMessage([$this->draft()], $other);

        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.drafts.save', [$otherMessage->id, 0]), $this->savePayload())
            ->assertNotFound();

        $message = $this->assistantMessage([$this->draft()]);
        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.drafts.save', [$message->id, 0]), [
            ...$this->savePayload(),
            'account_id' => $this->createAccount($other)->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_pending_action_is_confirmed_and_marked_resolved(): void
    {
        $token = str_repeat('a', 48);
        $this->runtime->next['content'] = 'Proposed financial change: ... send `/confirm '.$token.'`';
        $this->runtime->next['actions'] = [['type' => 'confirm_tool', 'tool' => 'createBudget', 'token' => $token, 'preview' => ['amount' => 1]]];

        $send = $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.send'), ['message' => 'create a food budget']);
        $send->assertOk()
            ->assertJsonPath('message.meta.actions.0.status', 'pending')
            ->assertJsonPath('message.meta.actions.0.token', $token);
        $this->assertStringNotContainsString($token, $send->json('message.content'));

        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.confirm'), [
            'conversation_id' => $send->json('conversation_id'),
            'token' => $token,
        ])->assertOk()->assertJsonPath('message.content', 'The financial change was completed successfully.');

        $this->assertSame([$token], $this->runtime->confirmCalls);

        $source = AdvisorMessage::find($send->json('message.id'));
        $this->assertSame('resolved', $source->meta['actions'][0]['status']);

        $page = app(AssistantService::class)->messagePayload($source);
        $this->assertArrayNotHasKey('token', $page['meta']['actions'][0]);
    }

    public function test_failed_voice_note_title_is_replaced_by_next_typed_message(): void
    {
        config(['services.gemini.api_key' => null]);

        $voice = $this->actingAs($this->user)->post(route('dashboard.finance.assistant.send'), [
            // ID3 header so the server-side MIME sniff sees real audio
            'audio' => UploadedFile::fake()->createWithContent('recording.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\x00", 512)),
            'language' => 'vi',
        ], ['Accept' => 'application/json']);

        $voice->assertOk()->assertJsonPath('message.meta.error', true);
        $conversationId = $voice->json('conversation_id');
        $this->assertSame('Ghi âm', AdvisorConversation::find($conversationId)->title);

        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.send'), [
            'conversation_id' => $conversationId,
            'message' => 'cafe sáng nay bao nhiêu?',
        ])->assertOk();

        $this->assertSame('cafe sáng nay bao nhiêu?', AdvisorConversation::find($conversationId)->title);
    }

    public function test_typed_title_is_not_overwritten_by_later_messages(): void
    {
        $first = $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.send'), ['message' => 'first question']);
        $conversationId = $first->json('conversation_id');

        $this->actingAs($this->user)->postJson(route('dashboard.finance.assistant.send'), [
            'conversation_id' => $conversationId,
            'message' => 'second question',
        ])->assertOk();

        $this->assertSame('first question', AdvisorConversation::find($conversationId)->title);
    }

    public function test_voice_is_routed_to_gemini_only(): void
    {
        $service = app(SmartInputParsingService::class);
        $this->user->update(['ai_settings' => ['provider' => 'claude', 'claude' => 'sk-test']]);
        config(['services.gemini.api_key' => null]);

        $this->assertNull($service->voiceParser($this->user->fresh()));

        config(['services.gemini.api_key' => 'gm-test']);

        $this->assertInstanceOf(GeminiTransactionParser::class, $service->voiceParser($this->user->fresh()));
    }

    public function test_send_returns_the_just_sent_user_message_not_the_first_one(): void
    {
        $conversation = AdvisorConversation::create(['user_id' => $this->user->id, 'title' => 'Ghi âm']);

        // An earlier failed voice turn; messages() orders by created_at, so it must be older
        $this->travel(-1)->hours();
        $conversation->messages()->create(['role' => 'user', 'content' => 'Ghi âm', 'meta' => ['input_type' => 'voice']]);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'Voice failed', 'meta' => ['error' => true]]);
        $this->travelBack();

        foreach (['web' => route('dashboard.finance.assistant.send'), 'api' => '/api/v1/finance/assistant/send'] as $channel => $url) {
            $response = $this->actingAs($this->user)->postJson($url, [
                'conversation_id' => $conversation->id,
                'message' => "how much did I spend ({$channel})?",
            ])->assertOk();

            $path = $channel === 'api' ? 'data.user_message.content' : 'user_message.content';
            $this->assertSame("how much did I spend ({$channel})?", $response->json($path));
        }
    }

    public function test_api_send_and_paginated_messages(): void
    {
        $send = $this->actingAs($this->user)->postJson('/api/v1/finance/assistant/send', ['message' => 'how am I doing?']);

        $send->assertOk()
            ->assertJsonPath('data.message.content', 'Hello')
            ->assertJsonPath('data.user_message.content', 'how am I doing?');

        $conversationId = $send->json('data.conversation.id');

        $this->actingAs($this->user)->getJson("/api/v1/finance/assistant/conversations/{$conversationId}/messages?limit=1")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.role', 'assistant')
            ->assertJsonPath('data.has_more', true);

        $other = User::factory()->create();
        $this->actingAs($other)->getJson("/api/v1/finance/assistant/conversations/{$conversationId}/messages")
            ->assertNotFound();
    }

    private function createAccount(User $user): Account
    {
        return Account::create([
            'user_id' => $user->id,
            'name' => 'Wallet '.$user->id,
            'account_type' => 'cash',
            'currency_code' => 'VND',
            'initial_balance' => 1000000,
            'current_balance' => 1000000,
            'is_active' => true,
        ]);
    }

    private function history(): SmartInputHistory
    {
        return SmartInputHistory::create([
            'user_id' => $this->user->id,
            'input_type' => 'text',
            'raw_text' => 'cafe 50k',
            'parsed_result' => ['amount' => 50000, 'description' => 'Coffee'],
            'language' => 'vi',
        ]);
    }

    /** @return array<string, mixed> */
    private function draft(array $overrides = []): array
    {
        return [
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Coffee',
            'suggested_category' => null,
            'suggested_account' => ['id' => $this->account->id, 'name' => $this->account->name],
            'suggested_transfer_account' => null,
            'transaction_date' => '2026-10-08',
            'confidence' => 0.8,
            'raw_text' => 'cafe 50k',
            'notes' => null,
            'history_id' => null,
            ...$overrides,
        ];
    }

    private function assistantMessage(array $drafts, ?User $owner = null): AdvisorMessage
    {
        $conversation = AdvisorConversation::create(['user_id' => ($owner ?? $this->user)->id]);

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Review',
            'meta' => ['drafts' => $drafts],
        ]);
    }

    /** @return array<string, mixed> */
    private function savePayload(): array
    {
        return [
            'type' => 'expense',
            'amount' => 50000,
            'description' => 'Coffee',
            'account_id' => $this->account->id,
            'transaction_date' => '2026-10-08',
        ];
    }
}
