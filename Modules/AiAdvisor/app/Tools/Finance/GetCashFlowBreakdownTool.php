<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetCashFlowBreakdownTool extends AbstractFinanceTool
{
    protected const NAME = 'getCashFlowBreakdown';

    protected const DESCRIPTION = 'Cash flow in the default currency split into passive income (e.g. rent, interest, dividends) vs active income, and essential vs other expenses, with passive coverage of essential expenses and savings rate. Defaults to the current month; use group_by=month for a per-month trend.';

    protected const METHOD = 'getCashFlowBreakdown';

    public function rules(): array
    {
        return ['start_date' => ['sometimes', 'date_format:Y-m-d'], 'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'group_by' => ['sometimes', 'in:total,month']];
    }
}
