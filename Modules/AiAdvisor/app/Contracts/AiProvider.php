<?php

namespace Modules\AiAdvisor\Contracts;

use App\Models\User;

interface AiProvider
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  string|null  $provider  provider key; null uses the server-configured provider
     * @return array<string, mixed>
     */
    public function complete(string $systemPrompt, array $messages, array $tools, ?User $actor = null, ?string $provider = null): array;
}
