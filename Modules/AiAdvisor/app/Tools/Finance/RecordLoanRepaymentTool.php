<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\Finance\Services\DebtToolService;

class RecordLoanRepaymentTool extends AbstractFinanceTool
{
    protected const NAME = 'recordLoanRepayment';

    protected const DESCRIPTION = 'Record that someone paid back part or all of a loan, after explicit user confirmation. Pass account_id only when the money arrived in one of the user\'s accounts; that also records the income.';

    protected const METHOD = 'recordLoanRepayment';

    protected const RISK = ToolRiskLevel::Write;

    protected const SERVICE = DebtToolService::class;

    public function rules(): array
    {
        return ['loan_id' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0'], 'repaid_at' => ['sometimes', 'date_format:Y-m-d'], 'note' => ['sometimes', 'nullable', 'string', 'max:255'], 'account_id' => ['sometimes', 'nullable', 'integer'], 'category_id' => ['sometimes', 'nullable', 'integer']];
    }
}
