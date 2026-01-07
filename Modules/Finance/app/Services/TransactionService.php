<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Events\TransactionCreated;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

class TransactionService
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

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

            // Calculate converted amount for cross-currency transfers
            $convertedAmount = $amount;
            $exchangeRate = null;

            if ($fromAccount->currency_code !== $toAccount->currency_code) {
                // Use source account's rate_source preference, or fall back to best available
                $rateSource = $fromAccount->rate_source;

                $exchangeRate = $this->exchangeRateService->getRate(
                    $fromAccount->currency_code,
                    $toAccount->currency_code,
                    $rateSource
                );

                if (! $exchangeRate) {
                    throw new \Exception(
                        "Exchange rate not found for {$fromAccount->currency_code} to {$toAccount->currency_code}"
                    );
                }

                $convertedAmount = (int) round($amount * $exchangeRate);
            }

            $description = $data['description'] ?? "Transfer to {$toAccount->name}";
            if ($exchangeRate) {
                $description .= ' (Rate: '.number_format($exchangeRate, 4).')';
            }

            $debitTransaction = $this->recordExpense([
                'account_id' => $fromAccount->id,
                'amount' => $amount,
                'description' => $description,
                'transaction_date' => $data['transaction_date'],
                'category_id' => null,
            ]);

            $creditDescription = $data['description'] ?? "Transfer from {$fromAccount->name}";
            if ($exchangeRate) {
                $creditDescription .= ' (Rate: '.number_format($exchangeRate, 4).')';
            }

            $creditTransaction = $this->recordIncome([
                'account_id' => $toAccount->id,
                'amount' => $convertedAmount,
                'description' => $creditDescription,
                'transaction_date' => $data['transaction_date'],
                'category_id' => null,
            ]);

            // Link transactions
            $debitTransaction->update([
                'transfer_account_id' => $toAccount->id,
                'transfer_transaction_id' => $creditTransaction->id,
            ]);

            $creditTransaction->update([
                'transfer_account_id' => $fromAccount->id,
                'transfer_transaction_id' => $debitTransaction->id,
            ]);

            return [
                'debit' => $debitTransaction,
                'credit' => $creditTransaction,
                'exchange_rate' => $exchangeRate,
                'converted_amount' => $convertedAmount,
            ];
        });
    }

    /**
     * Get conversion preview for cross-currency transfer
     */
    public function getTransferConversionPreview(int $fromAccountId, int $toAccountId, int $amount): array
    {
        $fromAccount = Account::findOrFail($fromAccountId);
        $toAccount = Account::findOrFail($toAccountId);

        if ($fromAccount->currency_code === $toAccount->currency_code) {
            return [
                'same_currency' => true,
                'amount' => $amount,
                'converted_amount' => $amount,
                'exchange_rate' => 1,
                'from_currency' => $fromAccount->currency_code,
                'to_currency' => $toAccount->currency_code,
            ];
        }

        $rateSource = $fromAccount->rate_source;
        $exchangeRate = $this->exchangeRateService->getRate(
            $fromAccount->currency_code,
            $toAccount->currency_code,
            $rateSource
        );

        if (! $exchangeRate) {
            return [
                'error' => "Exchange rate not found for {$fromAccount->currency_code} to {$toAccount->currency_code}",
                'same_currency' => false,
                'from_currency' => $fromAccount->currency_code,
                'to_currency' => $toAccount->currency_code,
            ];
        }

        return [
            'same_currency' => false,
            'amount' => $amount,
            'converted_amount' => (int) round($amount * $exchangeRate),
            'exchange_rate' => $exchangeRate,
            'from_currency' => $fromAccount->currency_code,
            'to_currency' => $toAccount->currency_code,
            'rate_source' => $rateSource ?? 'default',
        ];
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
