<?php

namespace Modules\Finance\Services;

use Modules\Settings\Services\AiCredentialResolver;

class FinancialAdvisorService
{
    public function __construct(
        protected FinancialReportContextBuilder $reportContextBuilder,
    ) {}

    public function isConfigured(): bool
    {
        $provider = config('services.smart_input.provider', 'deepseek');

        if ($provider === 'ollama') {
            return (bool) config('services.ollama.base_url');
        }

        return in_array($provider, AiCredentialResolver::PROVIDERS, true)
            && app(AiCredentialResolver::class)->apiKey($provider) !== null;
    }

    public function appendChartBlocks(string $content, string $userContent, int $userId): string
    {
        $chartBlocks = $this->reportContextBuilder->buildChartBlocksForQuestion($userId, $userContent);

        if (empty($chartBlocks)) {
            return $content;
        }

        $serializedBlocks = collect($chartBlocks)
            ->map(fn (array $chart) => "```finance-chart\n".json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n```")
            ->implode("\n\n");

        return trim($content)."\n\n".$serializedBlocks;
    }
}
