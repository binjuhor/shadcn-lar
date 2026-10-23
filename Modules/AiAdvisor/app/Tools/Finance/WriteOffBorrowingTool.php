<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\Finance\Services\DebtToolService;

class WriteOffBorrowingTool extends AbstractFinanceTool
{
    protected const NAME = 'writeOffBorrowing';

    protected const DESCRIPTION = 'Mark an outstanding borrowing as written off (forgiven or settled otherwise), after explicit user confirmation.';

    protected const METHOD = 'writeOffBorrowing';

    protected const RISK = ToolRiskLevel::Write;

    protected const SERVICE = DebtToolService::class;

    public function rules(): array
    {
        return ['borrowing_id' => ['required', 'integer']];
    }
}
