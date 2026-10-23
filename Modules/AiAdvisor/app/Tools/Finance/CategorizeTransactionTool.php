<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;

class CategorizeTransactionTool extends AbstractFinanceTool
{
    protected const NAME = 'categorizeTransaction';

    protected const DESCRIPTION = 'Assign a category to an existing transaction after explicit user confirmation.';

    protected const METHOD = 'categorizeTransaction';

    protected const RISK = ToolRiskLevel::Write;

    public function rules(): array
    {
        return ['transaction_id' => ['required', 'integer'], 'category_id' => ['required', 'integer']];
    }
}
