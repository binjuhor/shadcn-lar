<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\Finance\Services\DebtToolService;

class GetLoansTool extends AbstractFinanceTool
{
    protected const NAME = 'getLoans';

    protected const DESCRIPTION = 'List money the user lent to others (receivables). Filter by status, counterparty name (partial match), or overdue. Use it to find a loan_id before recording a repayment.';

    protected const METHOD = 'getLoans';

    protected const SERVICE = DebtToolService::class;

    public function rules(): array
    {
        return ['status' => ['sometimes', 'in:outstanding,active,partially_repaid,repaid,written_off'], 'counterparty' => ['sometimes', 'string', 'max:100'], 'overdue' => ['sometimes', 'boolean'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:50']];
    }
}
