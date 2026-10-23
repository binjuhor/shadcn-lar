<?php

namespace Modules\AiAdvisor\Contracts;

use App\Models\User;

interface Agent
{
    public function systemPrompt(User $actor): string;

    /** @return array<int, string> */
    public function allowedTools(): array;

    /**
     * Provider key to call, or null to use the server-configured provider.
     */
    public function provider(User $actor): ?string;
}
