<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Enums\ToolRiskLevel;

class CreateTransferTool extends AbstractFinanceTool
{
    protected const NAME = 'createTransfer';

    protected const DESCRIPTION = 'Move money between two of the user\'s own accounts, after explicit user confirmation. Cross-currency transfers use the stored exchange rate.';

    protected const METHOD = 'createTransfer';

    protected const RISK = ToolRiskLevel::Write;

    public function rules(): array
    {
        return ['from_account_id' => ['required', 'integer'], 'to_account_id' => ['required', 'integer', 'different:from_account_id'], 'amount' => ['required', 'numeric', 'gt:0'], 'transaction_date' => ['required', 'date_format:Y-m-d'], 'description' => ['sometimes', 'nullable', 'string', 'max:255'], 'notes' => ['sometimes', 'nullable', 'string', 'max:2000']];
    }
}
