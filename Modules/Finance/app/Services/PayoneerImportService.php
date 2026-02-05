<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\{Account, Category, Transaction};

class PayoneerImportService
{
    protected array $categoryMappings = [
        // Card charges - mapped to expense categories
        'CLAUDE.AI' => 'Software & Subscriptions',
        'CLOUDFLARE' => 'Software & Subscriptions',
        'CLOUDCONE' => 'Software & Subscriptions',
        'WISERAPP' => 'Software & Subscriptions',
        'CANIFA' => 'Shopping',
        'MYINDIEBOOK' => 'Education',
        'Kindle' => 'Education',

        // Fees
        'Annual card fee' => 'Bank Fees',
        'Transaction fee' => 'Bank Fees',

        // Withdrawals - Transfer out
        'Withdrawal to' => 'Transfer',

        // Payments - Income
        'Payment from' => 'Business Income',
    ];

    /**
     * Parse Payoneer CSV file and return structured data
     */
    public function parseCSV(string $filePath): Collection
    {
        $transactions = collect();

        if (($handle = fopen($filePath, 'r')) !== false) {
            // Skip header row
            $headers = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 10) {
                    continue;
                }

                $parsed = $this->parseRow($row);
                if ($parsed) {
                    $transactions->push($parsed);
                }
            }

            fclose($handle);
        }

        return $transactions;
    }

    /**
     * Parse a single CSV row
     */
    protected function parseRow(array $row): ?array
    {
        [$currency, $payoutMethod, $date, $time, $timezone, $credit, $debit, $status, $balance, $description] = $row;

        // Skip non-completed transactions
        if (strtolower($status) !== 'completed') {
            return null;
        }

        // Parse amounts (remove commas if any)
        // Payoneer CSV: Credit is positive, Debit is negative (with minus sign)
        $creditAmount = (float) str_replace(',', '', $credit);
        $debitAmount = abs((float) str_replace(',', '', $debit)); // Take absolute value

        // Determine transaction type based on which amount is present
        if ($creditAmount > 0) {
            $type = 'income';
            $amount = $creditAmount;
        } elseif ($debitAmount > 0) {
            $type = 'expense';
            $amount = $debitAmount;
        } else {
            // Skip zero amounts
            return null;
        }

        // Check if this is a withdrawal (transfer)
        $isTransfer = str_contains(strtolower($description), 'withdrawal to');

        // Parse date (format: MM-DD-YYYY)
        $transactionDate = Carbon::createFromFormat('m-d-Y', $date);

        // Suggest category based on description
        $suggestedCategory = $this->suggestCategory($description);

        return [
            'currency' => $currency,
            'transaction_date' => $transactionDate->format('Y-m-d'),
            'transaction_time' => $time,
            'timezone' => $timezone,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'running_balance' => (float) str_replace(',', '', $balance),
            'suggested_category' => $suggestedCategory,
            'is_transfer' => $isTransfer,
            'original_row' => $row,
        ];
    }

    /**
     * Suggest a category based on description
     */
    protected function suggestCategory(string $description): ?string
    {
        $descLower = strtolower($description);

        foreach ($this->categoryMappings as $keyword => $category) {
            if (str_contains($descLower, strtolower($keyword))) {
                return $category;
            }
        }

        // Default categories based on type patterns
        if (str_contains($descLower, 'card charge')) {
            return 'Other Expenses';
        }

        if (str_contains($descLower, 'payment from')) {
            return 'Business Income';
        }

        return null;
    }

    /**
     * Import parsed transactions into the database
     */
    public function importTransactions(
        Collection $transactions,
        int $accountId,
        int $userId,
        array $categoryMappings = [],
        bool $skipDuplicates = true
    ): array {
        $account = Account::findOrFail($accountId);

        // Verify account belongs to user
        if ($account->user_id !== $userId) {
            throw new \Exception('Account does not belong to user');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use (
            $transactions,
            $account,
            $userId,
            $categoryMappings,
            $skipDuplicates,
            &$imported,
            &$skipped,
            &$errors
        ) {
            foreach ($transactions as $tx) {
                try {
                    // Skip transfers for now (they need special handling)
                    if ($tx['is_transfer']) {
                        // For withdrawals, still record as expense
                        $tx['type'] = 'expense';
                    }

                    // Check for duplicates
                    if ($skipDuplicates) {
                        $exists = Transaction::where('account_id', $account->id)
                            ->where('transaction_date', $tx['transaction_date'])
                            ->where('amount', $tx['amount'])
                            ->where('description', $tx['description'])
                            ->exists();

                        if ($exists) {
                            $skipped++;
                            continue;
                        }
                    }

                    // Find category
                    $categoryId = null;
                    $suggestedCategory = $categoryMappings[$tx['description']] ?? $tx['suggested_category'];

                    if ($suggestedCategory) {
                        $category = Category::userCategories($userId)
                            ->where('name', $suggestedCategory)
                            ->first();

                        $categoryId = $category?->id;
                    }

                    // Create transaction
                    $transaction = Transaction::create([
                        'user_id' => $userId,
                        'account_id' => $account->id,
                        'category_id' => $categoryId,
                        'transaction_type' => $tx['type'],
                        'amount' => $tx['amount'],
                        'currency_code' => $account->currency_code,
                        'description' => $tx['description'],
                        'notes' => "Imported from Payoneer ({$tx['transaction_time']} {$tx['timezone']})",
                        'transaction_date' => $tx['transaction_date'],
                    ]);

                    // Update account balance
                    if ($tx['type'] === 'income') {
                        $account->updateBalance($tx['amount']);
                    } else {
                        $account->updateBalance(-$tx['amount']);
                    }

                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'description' => $tx['description'],
                        'date' => $tx['transaction_date'],
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => $transactions->count(),
        ];
    }

    /**
     * Get available categories for mapping
     */
    public function getCategories(int $userId): Collection
    {
        return Category::userCategories($userId)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);
    }
}
