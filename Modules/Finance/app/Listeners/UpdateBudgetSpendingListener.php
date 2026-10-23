<?php

namespace Modules\Finance\Listeners;

use Modules\Finance\Events\TransactionCreated;
use Modules\Finance\Services\BudgetService;

class UpdateBudgetSpendingListener
{
    public function __construct(private BudgetService $budgetService) {}

    public function handle(TransactionCreated $event): void
    {
        $transaction = $event->transaction;

        if ($transaction->transaction_type !== 'expense' || ! $transaction->category_id) {
            return;
        }

        $this->budgetService->syncBudgetsForCategory(
            $transaction->user_id,
            $transaction->category_id,
            $transaction->transaction_date->toDateString()
        );
    }
}
