<?php

namespace Modules\AiAdvisor\Tools\Finance;

class SuggestSavingPlanTool extends AbstractFinanceTool
{
    protected const NAME = 'suggestSavingPlan';

    protected const DESCRIPTION = 'Calculate monthly saving amounts for active goals without saving changes.';

    protected const METHOD = 'suggestSavingPlan';

    public function rules(): array
    {
        return [];
    }
}
