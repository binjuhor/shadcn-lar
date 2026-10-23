<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetTransactionsTool extends AbstractFinanceTool
{
    protected const NAME = 'getTransactions';

    protected const DESCRIPTION = 'List the authenticated user’s transactions with bounded filters and pagination.';

    protected const METHOD = 'getTransactions';

    public function rules(): array
    {
        return [...$this->dateRangeRules(), 'account_id' => ['sometimes', 'integer'], 'category_id' => ['sometimes', 'integer'], 'transaction_type' => ['sometimes', 'in:income,expense,transfer'], 'page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'between:1,50']];
    }
}
