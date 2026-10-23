<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetAccountBalancesTool extends AbstractFinanceTool
{
    protected const NAME = 'getAccountBalances';

    protected const DESCRIPTION = 'Get account balances and totals grouped by currency.';

    protected const METHOD = 'getAccountBalances';

    public function rules(): array
    {
        return [];
    }
}
