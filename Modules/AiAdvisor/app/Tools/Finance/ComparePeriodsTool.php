<?php

namespace Modules\AiAdvisor\Tools\Finance;

class ComparePeriodsTool extends AbstractFinanceTool
{
    protected const NAME = 'comparePeriods';

    protected const DESCRIPTION = 'Compare income, expenses, and cash flow across two explicit periods.';

    protected const METHOD = 'comparePeriods';

    public function rules(): array
    {
        return ['first_start' => ['required', 'date_format:Y-m-d'], 'first_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:first_start'], 'second_start' => ['required', 'date_format:Y-m-d'], 'second_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:second_start']];
    }
}
