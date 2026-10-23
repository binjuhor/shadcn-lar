<?php

namespace Modules\AiAdvisor\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AiAdvisor\Contracts\AiProvider;
use Modules\AiAdvisor\DTOs\ToolContext;
use Modules\AiAdvisor\Services\AgentRunner;
use RuntimeException;
use Tests\TestCase;

class AgentRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_executes_a_tool_and_returns_the_final_response(): void
    {
        $provider = new class implements AiProvider
        {
            public int $calls = 0;

            public function complete(string $systemPrompt, array $messages, array $tools, ?User $actor = null, ?string $provider = null): array
            {
                $this->calls++;

                if ($this->calls === 1) {
                    return ['content' => '', 'tool_calls' => [[
                        'id' => 'call-1',
                        'name' => 'getAccounts',
                        'arguments' => [],
                    ]]];
                }

                return ['content' => 'You have no accounts.', 'tool_calls' => [], 'usage' => ['prompt_tokens' => 10]];
            }
        };
        $this->app->instance(AiProvider::class, $provider);
        $actor = User::factory()->create();

        $result = app(AgentRunner::class)->run(
            new ToolContext($actor, 'correlation-1'),
            [['role' => 'user', 'content' => 'Show my accounts']],
        );

        $this->assertSame('You have no accounts.', $result->content);
        $this->assertSame(1, $result->toolRounds);
        $this->assertSame(2, $provider->calls);
    }

    public function test_runner_stops_at_the_configured_tool_round_limit(): void
    {
        config(['aiadvisor.max_tool_rounds' => 1]);
        $this->app->instance(AiProvider::class, new class implements AiProvider
        {
            public function complete(string $systemPrompt, array $messages, array $tools, ?User $actor = null, ?string $provider = null): array
            {
                return ['content' => '', 'tool_calls' => [['id' => 'loop', 'name' => 'getAccounts', 'arguments' => []]]];
            }
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('maximum tool rounds');

        app(AgentRunner::class)->run(
            new ToolContext(User::factory()->create(), 'correlation-loop'),
            [['role' => 'user', 'content' => 'Loop']],
        );
    }
}
