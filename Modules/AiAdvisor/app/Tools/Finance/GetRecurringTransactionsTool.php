<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetRecurringTransactionsTool extends AbstractFinanceTool
{
    protected const NAME = 'getRecurringTransactions';

    protected const DESCRIPTION = 'List upcoming recurring income and expenses (subscriptions, salary, rent) and the projected monthly recurring income, expense, and passive income.';

    protected const METHOD = 'getRecurringTransactions';

    public function rules(): array
    {
        return ['days' => ['sometimes', 'integer', 'min:1', 'max:90']];
    }
}
