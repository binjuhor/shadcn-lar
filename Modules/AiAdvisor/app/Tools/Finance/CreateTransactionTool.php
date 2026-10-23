<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;

class CreateTransactionTool extends AbstractFinanceTool
{
    protected const NAME = 'createTransaction';

    protected const DESCRIPTION = 'Create an income or expense transaction after explicit user confirmation.';

    protected const METHOD = 'createTransaction';

    protected const RISK = ToolRiskLevel::Write;

    public function rules(): array
    {
        return ['account_id' => ['required', 'integer'], 'category_id' => ['sometimes', 'nullable', 'integer'], 'transaction_type' => ['required', 'in:income,expense'], 'amount' => ['required', 'numeric', 'gt:0'], 'description' => ['sometimes', 'nullable', 'string', 'max:255'], 'notes' => ['sometimes', 'nullable', 'string', 'max:2000'], 'transaction_date' => ['required', 'date_format:Y-m-d']];
    }
}
