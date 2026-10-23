<?php

namespace Modules\AiAdvisor\Tools\Finance;

class AnalyzeSpendingPatternTool extends AbstractFinanceTool
{
    protected const NAME = 'analyzeSpendingPattern';

    protected const DESCRIPTION = 'Analyze expense count, total, and average by category and currency.';

    protected const METHOD = 'analyzeSpendingPattern';

    public function rules(): array
    {
        return $this->dateRangeRules();
    }
}
