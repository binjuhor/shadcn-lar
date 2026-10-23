<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\Finance\Services\DebtToolService;

class GetDebtSummaryTool extends AbstractFinanceTool
{
    protected const NAME = 'getDebtSummary';

    protected const DESCRIPTION = 'Summarize who owes the user and whom the user owes: outstanding totals by currency and by person, overdue items, and net position in the default currency.';

    protected const METHOD = 'getDebtSummary';

    protected const SERVICE = DebtToolService::class;

    public function rules(): array
    {
        return [];
    }
}
