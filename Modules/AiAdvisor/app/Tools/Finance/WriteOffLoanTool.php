<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\Finance\Services\DebtToolService;

class WriteOffLoanTool extends AbstractFinanceTool
{
    protected const NAME = 'writeOffLoan';

    protected const DESCRIPTION = 'Mark an outstanding loan as written off (forgiven or unrecoverable), after explicit user confirmation.';

    protected const METHOD = 'writeOffLoan';

    protected const RISK = ToolRiskLevel::Write;

    protected const SERVICE = DebtToolService::class;

    public function rules(): array
    {
        return ['loan_id' => ['required', 'integer']];
    }
}
