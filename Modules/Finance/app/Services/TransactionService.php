<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Events\TransactionCreated;
use Modules\Finance\Exceptions\ExchangeRateNotFoundException;
use Modules\Finance\Models\{Account, Currency, Transaction};

class TransactionService
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function recordIncome(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $userId = $data['user_id'] ?? auth()->id();
            $account = Account::where('user_id', $userId)->findOrFail($data['account_id']);
            $amount = $data['amount'];

            $transaction = Transaction::create([
                'user_id' => $userId,
                'account_id' => $account->id,
                'category_id' => $data['category_id'] ?? null,
                'transaction_type' => 'income',
                'amount' => $amount,
                'currency_code' => $account->currency_code,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);

            $account->updateBalance($amount);

            event(new TransactionCreated($transaction, $data['recurring_transaction_id'] ?? null));

            return $transaction;
        });
    }

    public function recordExpense(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $userId = $data['user_id'] ?? auth()->id();
            $account = Account::where('user_id', $userId)->findOrFail($data['account_id']);
            $amount = $data['amount'];

            // Allow recording expenses regardless of balance
            // Users may record historical transactions or track overdrafts

            $transaction = Transaction::create([
                'user_id' => $userId,
                'account_id' => $account->id,
                'category_id' => $data['category_id'] ?? null,
                'transaction_type' => 'expense',
                'amount' => $amount,
                'currency_code' => $account->currency_code,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
            ]);

            $account->updateBalance(-$amount);

            event(new TransactionCreated($transaction, $data['recurring_transaction_id'] ?? null));

            return $transaction;
        });
    }

    /**
     * Delete a single (non-transfer) transaction and undo its balance impact.
     */
    public function reverseAndDelete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $account = Account::query()->lockForUpdate()->find($transaction->account_id);
            $account?->updateBalance(-$transaction->balanceImpact());
            $transaction->delete();
        });
    }

    public function recordTransfer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Scope to the owner when the caller names one (e.g. the AI assistant)
            $accounts = fn () => isset($data['user_id']) ? Account::where('user_id', $data['user_id']) : Account::query();
            $fromAccount = $accounts()->findOrFail($data['from_account_id']);
            $toAccount = $accounts()->findOrFail($data['to_account_id']);
            $amount = $data['amount'];

            // Allow transfers regardless of balance for historical tracking
            // Users may record past transfers or track overdraft scenarios

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
                    throw new ExchangeRateNotFoundException(
                        "Exchange rate not found for {$fromAccount->currency_code} to {$toAccount->currency_code}"
                    );
                }

                $convertedAmount = $this->roundForCurrency($amount * $exchangeRate, $toAccount->currency_code);
            }

            $description = $data['description'] ?? "Transfer to {$toAccount->name}";
            if ($exchangeRate) {
                $description .= ' (Rate: '.number_format($exchangeRate, 4).')';
            }

            $debitTransaction = $this->recordExpense([
                'account_id' => $fromAccount->id,
                'user_id' => $fromAccount->user_id,
                'amount' => $amount,
                'description' => $description,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'category_id' => null,
            ]);

            $creditDescription = $data['description'] ?? "Transfer from {$fromAccount->name}";
            if ($exchangeRate) {
                $creditDescription .= ' (Rate: '.number_format($exchangeRate, 4).')';
            }

            $creditTransaction = $this->recordIncome([
                'account_id' => $toAccount->id,
                'user_id' => $fromAccount->user_id,
                'amount' => $convertedAmount,
                'description' => $creditDescription,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'category_id' => null,
            ]);

            // Link transactions
            $debitTransaction->update([
                'transaction_type' => 'transfer',
                'category_id' => null,
                'transfer_account_id' => $toAccount->id,
                'transfer_transaction_id' => $creditTransaction->id,
                'transfer_direction' => 'outgoing',
            ]);

            $creditTransaction->update([
                'transaction_type' => 'transfer',
                'category_id' => null,
                'transfer_account_id' => $fromAccount->id,
                'transfer_transaction_id' => $debitTransaction->id,
                'transfer_direction' => 'incoming',
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
    public function getTransferConversionPreview(int $fromAccountId, int $toAccountId, float $amount): array
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
            'converted_amount' => $this->roundForCurrency($amount * $exchangeRate, $toAccount->currency_code),
            'exchange_rate' => $exchangeRate,
            'from_currency' => $fromAccount->currency_code,
            'to_currency' => $toAccount->currency_code,
            'rate_source' => $rateSource ?? 'default',
        ];
    }

    /**
     * Rounds a converted amount to the target currency's minor unit (VND 0 places, USD 2); unknown codes use 2.
     */
    protected function roundForCurrency(float $amount, string $currencyCode): float
    {
        $places = Currency::whereKey($currencyCode)->value('decimal_places') ?? 2;

        return round($amount, (int) $places);
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
