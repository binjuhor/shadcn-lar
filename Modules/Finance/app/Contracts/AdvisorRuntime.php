<?php

namespace Modules\Finance\Contracts;

use App\Models\User;

interface AdvisorRuntime
{
    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array{content: string, prompt_tokens: ?int, completion_tokens: ?int}
     */
    public function run(User $actor, int $conversationId, array $history): array;

    /**
     * Unified assistant run: advice plus transaction drafts, using the user's own provider.
     *
     * @param  array<int, array<string, mixed>>  $history
     * @return array{content: string, prompt_tokens: ?int, completion_tokens: ?int, drafts: array, actions: array, provider: ?string}
     */
    public function assist(User $actor, int $conversationId, array $history): array;

    /** @return array{content: string, prompt_tokens: ?int, completion_tokens: ?int} */
    public function confirm(User $actor, int $conversationId, string $token): array;
}
