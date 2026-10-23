<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\Finance\Services\DebtToolService;

class CreateBorrowingTool extends AbstractFinanceTool
{
    protected const NAME = 'createBorrowing';

    protected const DESCRIPTION = 'Record money the user borrowed from someone, after explicit user confirmation. Pass account_id only when the money arrived in one of the user\'s accounts; that also records the income.';

    protected const METHOD = 'createBorrowing';

    protected const RISK = ToolRiskLevel::Write;

    protected const SERVICE = DebtToolService::class;

    public function rules(): array
    {
        return ['lender_name' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'gt:0'], 'currency_code' => ['sometimes', 'nullable', 'string', 'size:3', 'exists:currencies,code'], 'borrowed_date' => ['required', 'date_format:Y-m-d'], 'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:borrowed_date'], 'message' => ['sometimes', 'nullable', 'string', 'max:2000'], 'account_id' => ['sometimes', 'nullable', 'integer'], 'category_id' => ['sometimes', 'nullable', 'integer']];
    }
}
