<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Events\TransactionCreated;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

class TransactionService
{
    public function recordIncome(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $account = Account::findOrFail($data['account_id']);
            $amount = $data['amount'];

            $transaction = Transaction::create([
                'user_id' => auth()->id(),
                'account_id' => $account->id,
                'category_id' => $data['category_id'] ?? null,
                'transaction_type' => 'income',
                'amount' => $amount,
                'currency_code' => $account->currency_code,
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);

            $account->updateBalance($amount);

            event(new TransactionCreated($transaction));

            return $transaction;
        });
    }

    public function recordExpense(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $account = Account::findOrFail($data['account_id']);
            $amount = $data['amount'];

            if ($account->account_type !== 'credit_card') {
                if ($account->current_balance < $amount) {
                    throw new \Exception('Insufficient funds');
                }
            }

            $transaction = Transaction::create([
                'user_id' => auth()->id(),
                'account_id' => $account->id,
                'category_id' => $data['category_id'] ?? null,
                'transaction_type' => 'expense',
                'amount' => $amount,
                'currency_code' => $account->currency_code,
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);

            $account->updateBalance(-$amount);

            event(new TransactionCreated($transaction));

            return $transaction;
        });
    }

    public function recordTransfer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $fromAccount = Account::findOrFail($data['from_account_id']);
            $toAccount = Account::findOrFail($data['to_account_id']);
            $amount = $data['amount'];

            if ($fromAccount->current_balance < $amount) {
                throw new \Exception('Insufficient funds');
            }

            $debitTransaction = $this->recordExpense([
                'account_id' => $fromAccount->id,
                'amount' => $amount,
                'description' => $data['description'] ?? "Transfer to {$toAccount->name}",
                'transaction_date' => $data['transaction_date'],
                'category_id' => null,
            ]);

            $creditTransaction = $this->recordIncome([
                'account_id' => $toAccount->id,
                'amount' => $amount,
                'description' => $data['description'] ?? "Transfer from {$fromAccount->name}",
                'transaction_date' => $data['transaction_date'],
                'category_id' => null,
            ]);

            return [
                'debit' => $debitTransaction,
                'credit' => $creditTransaction,
            ];
        });
    }

    public function reconcileTransaction(int $transactionId): Transaction
    {
        $transaction = Transaction::findOrFail($transactionId);

        if ($transaction->isReconciled()) {
            throw new \Exception('Transaction already reconciled');
        }

        $transaction->update([
            'reconciled_at' => now(),
        ]);

        return $transaction;
    }
}
