<?php

namespace Modules\AiAdvisor\Services;

use Illuminate\Support\Facades\Log;
use Modules\AiAdvisor\Agents\FinancialAdvisorAgent;
use Modules\AiAdvisor\Contracts\{Agent, AiProvider};
use Modules\AiAdvisor\DTOs\{AgentRunResult, ToolCall, ToolContext};
use Modules\AiAdvisor\Enums\ToolRiskLevel;
use RuntimeException;

class AgentRunner
{
    public function __construct(
        private AiProvider $provider,
        private ToolRegistry $registry,
        private ToolExecutor $executor,
        private FinancialAdvisorAgent $defaultAgent,
    ) {}

    /** @param array<int, array<string, mixed>> $messages */
    public function run(ToolContext $context, array $messages, ?Agent $agent = null): AgentRunResult
    {
        $agent ??= $this->defaultAgent;
        $maxRounds = (int) config('aiadvisor.max_tool_rounds', 5);
        $provider = $agent->provider($context->actor);
        $systemPrompt = $agent->systemPrompt($context->actor);
        $allowedTools = $agent->allowedTools();
        $tools = $this->registry->definitions($allowedTools);
        $usage = [];
        $drafts = [];

        for ($round = 0; $round <= $maxRounds; $round++) {
            $response = $this->provider->complete($systemPrompt, $messages, $tools, $context->actor, $provider);
            $usage = $response['usage'] ?? $usage;
            Log::info('AI advisor provider completed', [
                'provider' => $provider ?? config('services.smart_input.provider'),
                'correlation_id' => $context->correlationId,
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
                'round' => $round,
            ]);
            $calls = $response['tool_calls'] ?? [];

            if ($calls === []) {
                return new AgentRunResult((string) ($response['content'] ?? ''), $usage, $round, $drafts, provider: $provider);
            }

            if ($round === $maxRounds) {
                throw new RuntimeException('AI advisor exceeded the maximum tool rounds.');
            }

            $messages[] = ['role' => 'assistant', 'content' => (string) ($response['content'] ?? ''), 'tool_calls' => $calls];

            foreach ($calls as $rawCall) {
                $call = new ToolCall(
                    (string) $rawCall['id'],
                    (string) $rawCall['name'],
                    (array) ($rawCall['arguments'] ?? []),
                );

                // Only tools advertised to this agent may run, even if another agent registered more
                if (! in_array($call->name, $allowedTools, true)) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call->id,
                        'name' => $call->name,
                        'content' => json_encode(['success' => false, 'error_code' => 'unknown_tool', 'message' => 'This tool is not available.'], JSON_THROW_ON_ERROR),
                    ];

                    continue;
                }

                $result = $this->executor->execute($context, $call);

                if ($result->success && $this->registry->get($call->name)->riskLevel() === ToolRiskLevel::Write) {
                    return new AgentRunResult('The confirmed financial change was completed successfully.', $usage, $round + 1, $drafts, provider: $provider);
                }

                if ($result->errorCode === 'confirmation_required') {
                    $token = $result->meta['confirmation_token'];
                    $preview = json_encode($result->meta['preview'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                    return new AgentRunResult(
                        "Proposed financial change: `{$preview}`. To apply it, send `/confirm {$token}`. This confirmation expires shortly.",
                        $usage,
                        $round + 1,
                        $drafts,
                        [[
                            'type' => 'confirm_tool',
                            'tool' => $call->name,
                            'token' => $token,
                            'preview' => $result->meta['preview'],
                        ]],
                        $provider,
                    );
                }

                // Drafts go to the client only; the model gets the compact summary in data
                $resultPayload = $result->toArray();
                if (isset($result->meta['drafts'])) {
                    $drafts = $this->mergeDrafts($drafts, $result->meta['drafts']);
                    unset($resultPayload['meta']);
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call->id,
                    'name' => $call->name,
                    'content' => json_encode($resultPayload, JSON_THROW_ON_ERROR),
                ];
            }
        }

        throw new RuntimeException('AI advisor stopped unexpectedly.');
    }

    /**
     * A model that re-calls the draft tool (retry or self-correction) must not produce
     * duplicate save buttons for the same transaction.
     *
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $new
     * @return list<array<string, mixed>>
     */
    private function mergeDrafts(array $existing, array $new): array
    {
        return collect([...$existing, ...$new])
            ->unique(fn (array $d) => implode('|', [$d['type'] ?? '', (float) ($d['amount'] ?? 0), mb_strtolower(trim((string) ($d['description'] ?? ''))), $d['transaction_date'] ?? '']))
            ->values()
            ->all();
    }
}
