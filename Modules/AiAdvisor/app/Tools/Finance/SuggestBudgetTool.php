<?php

namespace Modules\AiAdvisor\Tools\Finance;

class SuggestBudgetTool extends AbstractFinanceTool
{
    protected const NAME = 'suggestBudget';

    protected const DESCRIPTION = 'Suggest category budgets from recent expense averages without saving changes.';

    protected const METHOD = 'suggestBudget';

    public function rules(): array
    {
        return ['months' => ['sometimes', 'integer', 'between:1,12']];
    }
}
