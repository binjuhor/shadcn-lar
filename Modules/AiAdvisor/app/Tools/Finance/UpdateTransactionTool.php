<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;

class UpdateTransactionTool extends AbstractFinanceTool
{
    protected const NAME = 'updateTransaction';

    protected const DESCRIPTION = 'Update fields on an existing transaction after explicit user confirmation.';

    protected const METHOD = 'updateTransaction';

    protected const RISK = ToolRiskLevel::Write;

    public function rules(): array
    {
        return ['transaction_id' => ['required', 'integer'], 'account_id' => ['sometimes', 'integer'], 'category_id' => ['sometimes', 'nullable', 'integer'], 'transaction_type' => ['sometimes', 'in:income,expense'], 'amount' => ['sometimes', 'numeric', 'gt:0'], 'description' => ['sometimes', 'nullable', 'string', 'max:255'], 'notes' => ['sometimes', 'nullable', 'string', 'max:2000'], 'transaction_date' => ['sometimes', 'date_format:Y-m-d']];
    }
}
