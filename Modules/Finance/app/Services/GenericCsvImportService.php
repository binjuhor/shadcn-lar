<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\{Account, Category, Transaction};

class GenericCsvImportService
{
    protected array $categoryMappings = [
        'chuyen tien' => 'Transfer',
        'transfer' => 'Transfer',
        'luong' => 'Salary',
        'salary' => 'Salary',
        'lai' => 'Investment Income',
        'interest' => 'Investment Income',
        'loi nhuan' => 'Investment Income',
        'sinh loi' => 'Investment Income',
        'tikop' => 'Investment Income',
        'CCTG' => 'Investment Income',
        'Bao Loc' => 'Investment Income',
        'dien' => 'Utilities',
        'nuoc' => 'Utilities',
        'internet' => 'Utilities',
        'nap tien' => 'Utilities',
        'benh vien' => 'Healthcare',
        'grab' => 'Food & Dining',
        'shopee' => 'Shopping',
        'lazada' => 'Shopping',
        'tiki' => 'Shopping',
        'affiliate' => 'Affiliate Income',
        'phi' => 'Bank Fees',
        'fee' => 'Bank Fees',
        'no vay' => 'Loan Payment',
        'thanh toan' => 'Other Expenses',
    ];

    /**
     * Parse CSV file with standard headers:
     * Date, Remitter, Remitter Bank, Description, Transaction No, Debit, Credit, Balance
     */
    public function parseCSV(string $filePath): Collection
    {
        $transactions = collect();

        if (($handle = fopen($filePath, 'r')) !== false) {
            // Read header row
            $headers = fgetcsv($handle);

            // Normalize headers (lowercase, trimmed)
            $headers = array_map(fn ($h) => strtolower(trim($h ?? '')), $headers);

            // Map header positions
            $dateCol = array_search('date', $headers);
            $remitterCol = array_search('remitter', $headers);
            $bankCol = array_search('remitter bank', $headers);
            $descCol = array_search('description', $headers);
            $txNoCol = array_search('transaction no', $headers);
            $debitCol = array_search('debit', $headers);
            $creditCol = array_search('credit', $headers);
            $balanceCol = array_search('balance', $headers);

            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row) || count($row) < 3) {
                    continue;
                }

                $date = $row[$dateCol] ?? '';
                if (empty($date)) {
                    continue;
                }

                $debit = $this->parseAmount($row[$debitCol] ?? '');
                $credit = $this->parseAmount($row[$creditCol] ?? '');

                // Skip if no amount
                if ($debit == 0 && $credit == 0) {
                    continue;
                }

                $description = trim($row[$descCol] ?? '');
                $remitter = trim($row[$remitterCol] ?? '');
                $bank = trim($row[$bankCol] ?? '');

                // Build full description
                $fullDesc = $description;
                if ($remitter && ! str_contains($description, $remitter)) {
                    $fullDesc = $description . ' - ' . $remitter;
                }

                $transactionDate = $this->parseDate($date);
                if (! $transactionDate) {
                    continue;
                }

                $transactions->push([
                    'currency' => 'VND',
                    'transaction_date' => $transactionDate->format('Y-m-d'),
                    'transaction_time' => null,
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'type' => $credit > 0 ? 'income' : 'expense',
                    'amount' => $credit > 0 ? $credit : $debit,
                    'description' => $fullDesc ?: 'No description',
                    'running_balance' => $this->parseAmount($row[$balanceCol] ?? ''),
                    'suggested_category' => $this->suggestCategory($fullDesc),
                    'is_transfer' => $this->isTransfer($fullDesc),
                    'transaction_no' => trim($row[$txNoCol] ?? ''),
                    'remitter' => $remitter,
                    'remitter_bank' => $bank,
                    'original_description' => $description,
                ]);
            }

            fclose($handle);
        }

        return $transactions;
    }

    protected function parseAmount($value): float
    {
        if (empty($value)) {
            return 0.0;
        }

        // Remove spaces and currency symbols
        $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $value);
        // Remove commas used as thousands separator
        $cleaned = str_replace(',', '', $cleaned);

        return abs((float) $cleaned);
    }

    protected function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);

        try {
            // Try DD/MM/YYYY format first
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value);
            }

            // Try YYYY-MM-DD format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return Carbon::createFromFormat('Y-m-d', $value);
            }

            // Try MM/DD/YYYY format
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('m/d/Y', $value);
            }

            // Generic parse
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function isTransfer(string $description): bool
    {
        $descLower = strtolower($description);

        return str_contains($descLower, 'chuyen tien') ||
            str_contains($descLower, 'transfer') ||
            str_contains($descLower, 'chuyen khoan');
    }

    protected function suggestCategory(string $description): ?string
    {
        $descLower = strtolower($description);

        foreach ($this->categoryMappings as $keyword => $category) {
            if (str_contains($descLower, strtolower($keyword))) {
                return $category;
            }
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

                    $categoryId = null;
                    $suggestedCategory = $categoryMappings[$tx['description']] ?? $tx['suggested_category'];

                    if ($suggestedCategory) {
                        $category = Category::userCategories($userId)
                            ->where('name', $suggestedCategory)
                            ->first();

                        $categoryId = $category?->id;
                    }

                    Transaction::create([
                        'user_id' => $userId,
                        'account_id' => $account->id,
                        'category_id' => $categoryId,
                        'transaction_type' => $tx['type'],
                        'amount' => $tx['amount'],
                        'currency_code' => $account->currency_code,
                        'description' => $tx['description'],
                        'notes' => "Imported from CSV (Ref: {$tx['transaction_no']})",
                        'transaction_date' => $tx['transaction_date'],
                    ]);

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
}
