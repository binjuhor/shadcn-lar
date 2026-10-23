<?php

namespace Modules\AiAdvisor\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\AiAdvisor\Agents\FinanceAssistantAgent;
use Modules\AiAdvisor\DTOs\ToolCall;
use Modules\AiAdvisor\DTOs\ToolContext;
use Modules\Finance\Contracts\AdvisorRuntime;

class FinanceAdvisorRuntime implements AdvisorRuntime
{
    public function __construct(
        private AgentRunner $runner,
        private ToolConfirmationService $confirmations,
        private ToolExecutor $executor,
        private FinanceAssistantAgent $assistant,
    ) {}

    public function run(User $actor, int $conversationId, array $history): array
    {
        $run = $this->runner->run(new ToolContext(
            actor: $actor,
            correlationId: (string) Str::uuid(),
            conversationId: $conversationId,
            locale: app()->getLocale(),
        ), $history);

        return [
            'content' => $run->content,
            'prompt_tokens' => $run->usage['prompt_tokens'] ?? null,
            'completion_tokens' => $run->usage['completion_tokens'] ?? null,
        ];
    }

    public function assist(User $actor, int $conversationId, array $history): array
    {
        $run = $this->runner->run(new ToolContext(
            actor: $actor,
            correlationId: (string) Str::uuid(),
            conversationId: $conversationId,
            locale: app()->getLocale(),
        ), $history, $this->assistant);

        return [
            'content' => $run->content,
            'prompt_tokens' => $run->usage['prompt_tokens'] ?? null,
            'completion_tokens' => $run->usage['completion_tokens'] ?? null,
            'drafts' => $run->drafts,
            'actions' => $run->actions,
            'provider' => $run->provider,
        ];
    }

    public function confirm(User $actor, int $conversationId, string $token): array
    {
        $context = new ToolContext($actor, (string) Str::uuid(), $conversationId, app()->getLocale());
        $pending = $this->confirmations->pending($context, $token);

        if (! $pending) {
            return ['content' => 'This confirmation is invalid or expired.', 'prompt_tokens' => null, 'completion_tokens' => null];
        }

        $result = $this->executor->execute($context, new ToolCall(
            id: (string) Str::uuid(),
            name: $pending['tool'],
            arguments: $pending['arguments'],
            confirmed: true,
            confirmationToken: $token,
            idempotencyKey: hash('sha256', $token),
        ));

        return [
            'content' => $result->success
                ? 'The financial change was completed successfully.'
                : ($result->message ?? 'The financial change could not be completed.'),
            'prompt_tokens' => null,
            'completion_tokens' => null,
        ];
    }
}
