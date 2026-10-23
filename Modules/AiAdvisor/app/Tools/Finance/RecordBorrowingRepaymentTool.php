<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\Finance\Services\DebtToolService;

class RecordBorrowingRepaymentTool extends AbstractFinanceTool
{
    protected const NAME = 'recordBorrowingRepayment';

    protected const DESCRIPTION = 'Record that the user paid back part or all of a borrowing, after explicit user confirmation. Pass account_id only when the money left one of the user\'s accounts; that also records the expense.';

    protected const METHOD = 'recordBorrowingRepayment';

    protected const RISK = ToolRiskLevel::Write;

    protected const SERVICE = DebtToolService::class;

    public function rules(): array
    {
        return ['borrowing_id' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0'], 'repaid_at' => ['sometimes', 'date_format:Y-m-d'], 'note' => ['sometimes', 'nullable', 'string', 'max:255'], 'account_id' => ['sometimes', 'nullable', 'integer'], 'category_id' => ['sometimes', 'nullable', 'integer']];
    }
}
