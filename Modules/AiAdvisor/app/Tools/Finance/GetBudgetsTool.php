<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetBudgetsTool extends AbstractFinanceTool
{
    protected const NAME = 'getBudgets';

    protected const DESCRIPTION = 'List budgets and their current usage status.';

    protected const METHOD = 'getBudgets';

    public function rules(): array
    {
        return ['active' => ['sometimes', 'boolean']];
    }
}
