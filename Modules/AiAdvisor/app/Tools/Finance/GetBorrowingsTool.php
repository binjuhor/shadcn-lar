<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\Finance\Services\DebtToolService;

class GetBorrowingsTool extends AbstractFinanceTool
{
    protected const NAME = 'getBorrowings';

    protected const DESCRIPTION = 'List money the user borrowed from others (payables). Filter by status, counterparty name (partial match), or overdue. Use it to find a borrowing_id before recording a repayment.';

    protected const METHOD = 'getBorrowings';

    protected const SERVICE = DebtToolService::class;

    public function rules(): array
    {
        return ['status' => ['sometimes', 'in:outstanding,active,partially_repaid,repaid,written_off'], 'counterparty' => ['sometimes', 'string', 'max:100'], 'overdue' => ['sometimes', 'boolean'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:50']];
    }
}
