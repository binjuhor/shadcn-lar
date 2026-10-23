<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetFinancialSummaryTool extends AbstractFinanceTool
{
    protected const NAME = 'getFinancialSummary';

    protected const DESCRIPTION = 'Get income, expense, cash flow, balances, budgets, and goals for a date range.';

    protected const METHOD = 'getFinancialSummary';

    public function rules(): array
    {
        return $this->dateRangeRules();
    }
}
