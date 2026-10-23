<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;

class CreateGoalTool extends AbstractFinanceTool
{
    protected const NAME = 'createGoal';

    protected const DESCRIPTION = 'Create a saving goal after explicit user confirmation.';

    protected const METHOD = 'createGoal';

    protected const RISK = ToolRiskLevel::Write;

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string', 'max:2000'], 'target_account_id' => ['sometimes', 'nullable', 'integer'], 'target_amount' => ['required', 'numeric', 'gt:0'], 'currency_code' => ['required', 'string', 'size:3'], 'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:today']];
    }
}
