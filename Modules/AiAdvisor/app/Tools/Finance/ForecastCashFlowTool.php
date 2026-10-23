<?php

namespace Modules\AiAdvisor\Tools\Finance;

class ForecastCashFlowTool extends AbstractFinanceTool
{
    protected const NAME = 'forecastCashFlow';

    protected const DESCRIPTION = 'Forecast cash flow from historical monthly averages and disclose assumptions.';

    protected const METHOD = 'forecastCashFlow';

    public function rules(): array
    {
        return ['history_months' => ['sometimes', 'integer', 'between:1,12'], 'forecast_months' => ['sometimes', 'integer', 'between:1,12']];
    }
}
