<?php

namespace Modules\AiAdvisor\Agents;

use App\Models\User;
use Modules\AiAdvisor\Contracts\Agent;
use Modules\AiAdvisor\Tools\Finance\FinanceToolCatalog;

class FinancialAdvisorAgent implements Agent
{
    public function systemPrompt(?User $actor = null): string
    {
        $prompt = file_get_contents(module_path('AiAdvisor', 'Prompts/financial-advisor.md'));

        // Write tools need dates; relative ones ("yesterday") resolve against today
        return trim($prompt ?: 'You are a careful personal financial advisor.')."\n\n".$this->sharedRules()
            ."\n\nToday: ".now()->toDateString().' ('.now()->format('l').')';
    }

    /** Finance-only topic restriction and tool-routing guidance shared by every Mokey agent. */
    public function sharedRules(): string
    {
        return collect(['finance-scope.md', 'finance-tools.md'])
            ->map(fn (string $file) => trim((string) file_get_contents(module_path('AiAdvisor', "Prompts/{$file}"))))
            ->implode("\n\n");
    }

    /** @return array<int, string> */
    public function allowedTools(): array
    {
        return collect(FinanceToolCatalog::classes())
            ->map(fn (string $class) => app($class)->name())
            ->all();
    }

    public function provider(User $actor): ?string
    {
        return null;
    }
}
