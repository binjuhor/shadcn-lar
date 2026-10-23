<?php

namespace Modules\Finance\Listeners;

use Carbon\Carbon;
use Modules\Finance\Events\TransactionCreated;
use Modules\Finance\Models\RecurringTransaction;

class SyncRecurringTransactionFromTransactionListener
{
    public function handle(TransactionCreated $event): void
    {
        if ($event->recurringTransactionId !== null) {
            return;
        }

        $transaction = $event->transaction;
        $transactionDate = Carbon::parse($transaction->transaction_date)->startOfDay();

        $recurring = RecurringTransaction::where('user_id', $transaction->user_id)
            ->where('account_id', $transaction->account_id)
            ->where('category_id', $transaction->category_id)
            ->where('transaction_type', $transaction->transaction_type)
            ->where('currency_code', $transaction->currency_code)
            ->where('amount', $transaction->amount)
            ->where('is_active', true)
            ->where('auto_create', true)
            ->whereDate('next_run_date', '>=', $transactionDate->copy()->subDays(3)->toDateString())
            ->whereDate('next_run_date', '<=', $transactionDate->copy()->addDays(7)->toDateString())
            ->get()
            ->sortBy(fn (RecurringTransaction $recurring) => abs($recurring->next_run_date->diffInDays($transactionDate, false)))
            ->first(fn (RecurringTransaction $recurring) => $this->titlesMatch($recurring->name, $transaction->description));

        if (! $recurring) {
            return;
        }

        $nextRunDate = $recurring->next_run_date->copy();

        do {
            $nextRunDate = $recurring->calculateNextRunDate($nextRunDate);
        } while ($nextRunDate <= $transactionDate);

        $recurring->update([
            'last_run_date' => $transactionDate,
            'next_run_date' => $nextRunDate,
        ]);
    }

    private function titlesMatch(string $recurringName, ?string $transactionDescription): bool
    {
        if ($transactionDescription === null) {
            return false;
        }

        return $this->normalizeTitle($recurringName) === $this->normalizeTitle($transactionDescription);
    }

    private function normalizeTitle(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
