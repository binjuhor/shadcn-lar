<?php

namespace Modules\AiAdvisor\Agents;

use App\Models\User;
use Modules\AiAdvisor\Contracts\Agent;
use Modules\AiAdvisor\Tools\Finance\DraftTransactionsTool;
use Modules\Finance\Models\Currency;
use Modules\Settings\Services\AiCredentialResolver;

/**
 * Unified chat agent: financial advice (advisor tools) plus transaction tracking
 * through editable drafts. Uses the user's own provider from Settings.
 */
class FinanceAssistantAgent implements Agent
{
    public function __construct(
        private FinancialAdvisorAgent $advisor,
        private AiCredentialResolver $credentials,
    ) {}

    public function systemPrompt(User $actor): string
    {
        $prompt = trim((string) file_get_contents(module_path('AiAdvisor', 'Prompts/finance-assistant.md')));
        $currency = Currency::where('is_default', true)->value('code') ?? 'VND';

        // Relative dates ("yesterday") and shorthand amounts ("50k") need this context
        return $prompt."\n\n".$this->advisor->sharedRules()."\n\nContext:\n"
            .'- Today: '.now()->toDateString().' ('.now()->format('l').")\n"
            ."- Default currency: {$currency}\n"
            .'- User language preference: '.($actor->language ?: 'vi');
    }

    /** @return array<int, string> */
    public function allowedTools(): array
    {
        return [...$this->advisor->allowedTools(), DraftTransactionsTool::NAME];
    }

    public function provider(User $actor): ?string
    {
        return $this->credentials->provider($actor);
    }
}
