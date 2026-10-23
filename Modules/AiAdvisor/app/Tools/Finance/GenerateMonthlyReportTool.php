<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GenerateMonthlyReportTool extends AbstractFinanceTool
{
    protected const NAME = 'generateMonthlyReport';

    protected const DESCRIPTION = 'Generate a monthly financial summary and category spending report.';

    protected const METHOD = 'generateMonthlyReport';

    public function rules(): array
    {
        return ['month' => ['sometimes', 'date_format:Y-m']];
    }
}
