<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;

class UpdateBudgetTool extends AbstractFinanceTool
{
    protected const NAME = 'updateBudget';

    protected const DESCRIPTION = 'Update an existing budget after explicit user confirmation.';

    protected const METHOD = 'updateBudget';

    protected const RISK = ToolRiskLevel::Write;

    public function rules(): array
    {
        return ['budget_id' => ['required', 'integer'], 'name' => ['sometimes', 'string', 'max:255'], 'category_id' => ['sometimes', 'nullable', 'integer'], 'period_type' => ['sometimes', 'in:weekly,monthly,quarterly,yearly,custom'], 'allocated_amount' => ['sometimes', 'numeric', 'gt:0'], 'currency_code' => ['sometimes', 'string', 'size:3'], 'start_date' => ['sometimes', 'date_format:Y-m-d'], 'end_date' => ['sometimes', 'date_format:Y-m-d'], 'is_active' => ['sometimes', 'boolean'], 'rollover' => ['sometimes', 'boolean']];
    }
}
