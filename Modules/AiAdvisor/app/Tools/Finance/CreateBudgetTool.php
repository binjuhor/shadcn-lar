<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;

class CreateBudgetTool extends AbstractFinanceTool
{
    protected const NAME = 'createBudget';

    protected const DESCRIPTION = 'Create a budget after explicit user confirmation.';

    protected const METHOD = 'createBudget';

    protected const RISK = ToolRiskLevel::Write;

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'category_id' => ['sometimes', 'nullable', 'integer'], 'period_type' => ['required', 'in:weekly,monthly,quarterly,yearly,custom'], 'allocated_amount' => ['required', 'numeric', 'gt:0'], 'currency_code' => ['required', 'string', 'size:3'], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'rollover' => ['sometimes', 'boolean']];
    }
}
