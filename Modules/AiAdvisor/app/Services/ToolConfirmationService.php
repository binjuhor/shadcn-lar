<?php

namespace Modules\AiAdvisor\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use Modules\AiAdvisor\DTOs\{ToolCall, ToolContext};

class ToolConfirmationService
{
    public function __construct(private Repository $cache) {}

    public function issue(ToolContext $context, ToolCall $call): string
    {
        $token = Str::random(48);
        $this->cache->put($this->key($token), [
            'fingerprint' => $this->fingerprint($context, $call),
            'actor_id' => $context->actor->getKey(),
            'conversation_id' => $context->conversationId,
            'tool' => $call->name,
            'arguments' => $call->arguments,
        ], config('aiadvisor.confirmation_ttl_seconds', 600));

        return $token;
    }

    public function consume(ToolContext $context, ToolCall $call): bool
    {
        if (! $call->confirmationToken) {
            return false;
        }

        $pending = $this->cache->pull($this->key($call->confirmationToken), []);

        return is_array($pending)
            && isset($pending['fingerprint'])
            && hash_equals((string) $pending['fingerprint'], $this->fingerprint($context, $call));
    }

    /** @return array{tool: string, arguments: array<string, mixed>}|null */
    public function pending(ToolContext $context, string $token): ?array
    {
        $pending = $this->cache->get($this->key($token));

        if (! is_array($pending)) {
            return null;
        }

        if ((int) ($pending['actor_id'] ?? 0) !== (int) $context->actor->getKey()) {
            return null;
        }

        if ((int) ($pending['conversation_id'] ?? 0) !== (int) $context->conversationId) {
            return null;
        }

        return ['tool' => $pending['tool'], 'arguments' => $pending['arguments']];
    }

    private function key(string $token): string
    {
        return "aiadvisor:confirmation:{$token}";
    }

    private function fingerprint(ToolContext $context, ToolCall $call): string
    {
        $arguments = $call->arguments;
        ksort($arguments);

        return hash('sha256', json_encode([$context->actor->getKey(), $context->conversationId, $call->name, $arguments], JSON_THROW_ON_ERROR));
    }
}
