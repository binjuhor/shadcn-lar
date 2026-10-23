<?php

namespace Modules\AiAdvisor\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AiAdvisor\Agents\FinanceAssistantAgent;
use Modules\AiAdvisor\Contracts\AiProvider;
use Modules\AiAdvisor\DTOs\ToolContext;
use Modules\AiAdvisor\Services\AgentRunner;
use Modules\Finance\Models\{Account, Currency, SmartInputHistory, Transaction};
use Tests\TestCase;

class FinanceAssistantAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_drafts_transactions_without_writing_and_hides_drafts_from_the_model(): void
    {
        Currency::create(['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'decimal_places' => 0, 'is_default' => true]);
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->account($actor, 'Techcombank');
        $this->account($other, 'Vietcombank');

        $provider = $this->scriptedProvider([
            ['content' => '', 'tool_calls' => [[
                'id' => 'call-1',
                'name' => 'draftTransactions',
                'arguments' => ['items' => [
                    ['type' => 'expense', 'amount' => 50000, 'description' => 'Cafe', 'account_hint' => 'Techcombank'],
                    // Hint naming another user's account must not resolve to it
                    ['type' => 'expense', 'amount' => 35000, 'description' => 'Grab', 'account_hint' => 'Vietcombank'],
                ]],
            ]]],
            ['content' => 'Review the 2 drafts.', 'tool_calls' => []],
        ]);

        $result = app(AgentRunner::class)->run(
            new ToolContext($actor, 'correlation-assistant'),
            [['role' => 'user', 'content' => 'cafe 50k techcombank, grab 35k']],
            app(FinanceAssistantAgent::class),
        );

        $this->assertSame('Review the 2 drafts.', $result->content);
        $this->assertCount(2, $result->drafts);
        $this->assertSame($mine->id, $result->drafts[0]['suggested_account']['id']);
        $this->assertNotSame('Vietcombank', $result->drafts[1]['suggested_account']['name'] ?? null);
        $this->assertSame(2, SmartInputHistory::where('user_id', $actor->id)->count());
        $this->assertSame(0, Transaction::count());

        $toolMessage = collect($provider->received[1])->firstWhere('role', 'tool');
        $this->assertStringNotContainsString('suggested_account', $toolMessage['content']);
        $this->assertStringContainsString('Not saved yet', $toolMessage['content']);
        $this->assertContains('draftTransactions', array_column($provider->tools, 'name'));
    }

    public function test_repeated_draft_calls_do_not_duplicate_drafts(): void
    {
        $actor = User::factory()->create();
        $call = fn (string $id) => ['content' => '', 'tool_calls' => [[
            'id' => $id,
            'name' => 'draftTransactions',
            'arguments' => ['items' => [['type' => 'expense', 'amount' => 50000, 'description' => 'Cafe', 'transaction_date' => '2026-10-08']]],
        ]]];
        $this->scriptedProvider([$call('call-1'), $call('call-2'), ['content' => 'Done.', 'tool_calls' => []]]);

        $result = app(AgentRunner::class)->run(
            new ToolContext($actor, 'correlation-dup'),
            [['role' => 'user', 'content' => 'cafe 50k']],
            app(FinanceAssistantAgent::class),
        );

        $this->assertCount(1, $result->drafts);
    }

    public function test_advisor_agent_cannot_call_the_assistant_only_draft_tool(): void
    {
        $actor = User::factory()->create();
        $provider = $this->scriptedProvider([
            ['content' => '', 'tool_calls' => [[
                'id' => 'call-1',
                'name' => 'draftTransactions',
                'arguments' => ['items' => [['type' => 'expense', 'amount' => 1, 'description' => 'x']]],
            ]]],
            ['content' => 'Done.', 'tool_calls' => []],
        ]);

        $result = app(AgentRunner::class)->run(
            new ToolContext($actor, 'correlation-advisor'),
            [['role' => 'user', 'content' => 'draft it']],
        );

        $this->assertSame([], $result->drafts);
        $this->assertSame(0, SmartInputHistory::count());
        $this->assertNotContains('draftTransactions', array_column($provider->tools, 'name'));
        $this->assertStringContainsString('unknown_tool', collect($provider->received[1])->firstWhere('role', 'tool')['content']);
    }

    public function test_assistant_uses_the_users_selected_provider(): void
    {
        config(['services.smart_input.provider' => 'deepseek', 'services.gemini.api_key' => 'server-key']);
        $actor = User::factory()->create(['ai_settings' => ['provider' => 'gemini']]);
        $provider = $this->scriptedProvider([['content' => 'Hi', 'tool_calls' => []]]);

        $result = app(AgentRunner::class)->run(
            new ToolContext($actor, 'correlation-provider'),
            [['role' => 'user', 'content' => 'hi']],
            app(FinanceAssistantAgent::class),
        );

        $this->assertSame('gemini', $provider->providers[0]);
        $this->assertSame('gemini', $result->provider);
    }

    public function test_every_agent_restricts_the_model_to_finance_topics(): void
    {
        $actor = User::factory()->create();
        $provider = $this->scriptedProvider([
            ['content' => 'Only finance.', 'tool_calls' => []],
            ['content' => 'Only finance.', 'tool_calls' => []],
        ]);
        $context = new ToolContext($actor, 'correlation-scope');
        $messages = [['role' => 'user', 'content' => 'Solve my homework: 15 + 27']];

        app(AgentRunner::class)->run($context, $messages);
        app(AgentRunner::class)->run($context, $messages, app(FinanceAssistantAgent::class));

        $this->assertCount(2, $provider->systemPrompts);
        foreach ($provider->systemPrompts as $prompt) {
            $this->assertStringContainsString('Scope (strict', $prompt);
            $this->assertStringContainsString('homework', $prompt);
        }
    }

    /** @param list<array<string, mixed>> $responses */
    private function scriptedProvider(array $responses): object
    {
        $provider = new class($responses) implements AiProvider
        {
            public array $received = [];

            public array $providers = [];

            public array $tools = [];

            public array $systemPrompts = [];

            public function __construct(private array $responses) {}

            public function complete(string $systemPrompt, array $messages, array $tools, ?User $actor = null, ?string $provider = null): array
            {
                $this->systemPrompts[] = $systemPrompt;
                $this->received[] = $messages;
                $this->providers[] = $provider;
                $this->tools = $tools;

                return array_shift($this->responses);
            }
        };
        $this->app->instance(AiProvider::class, $provider);

        return $provider;
    }

    private function account(User $user, string $name): Account
    {
        return Account::create([
            'user_id' => $user->id,
            'name' => $name,
            'account_type' => 'bank',
            'currency_code' => 'VND',
            'initial_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);
    }
}
