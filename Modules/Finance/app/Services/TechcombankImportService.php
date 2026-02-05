<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\{Account, Category, Transaction};
use PhpOffice\PhpSpreadsheet\IOFactory;

class TechcombankImportService
{
    protected const DATA_START_ROW = 36;

    // Column letters based on actual Techcombank statement structure
    protected const COL_DATE = 'B';
    protected const COL_REMITTER = 'H';
    protected const COL_REMITTER_BANK = 'Q';
    protected const COL_DESCRIPTION = 'Y';
    protected const COL_TRANSACTION_NO = 'AG';
    protected const COL_DEBIT = 'AT';
    protected const COL_CREDIT = 'BB';
    protected const COL_BALANCE = 'BH';

    protected array $categoryMappings = [
        // Transfer keywords
        'chuyen tien' => 'Transfer',
        'transfer' => 'Transfer',

        // Income patterns
        'luong' => 'Salary',
        'salary' => 'Salary',
        'interest' => 'Investment Income',
        'tikop' => 'Investment Income',
        'Shopee affiliate' => 'Affiliate Income',
        'lai suat' => 'Investment Income',
        'tra lai so du tren tai khoan' => 'Other Income',

        // Expense patterns
        'dien' => 'Utilities',
        'nuoc' => 'Utilities',
        'internet' => 'Utilities',
        'fpt' => 'Utilities',
        'viettel' => 'Utilities',
        'vnpt' => 'Utilities',
        'NGUYEN VIET TRI' => 'Business Expenses',
        'HA THI LAN' => 'Gifts & Donations',
        'NGUYEN THI NGOC' => 'Home & Family',

        // Food & Dining
        'grab' => 'Food & Dining',
        'shopee' => 'Shopping',
        'lazada' => 'Shopping',
        'tiki' => 'Shopping',

        // Bank fees
        'phi' => 'Bank Fees',
        'fee' => 'Bank Fees',
    ];

    /**
     * Parse Techcombank Excel file and return structured data
     */
    public function parseExcel(string $filePath): Collection
    {
        $transactions = collect();

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();

        for ($row = self::DATA_START_ROW; $row <= $highestRow; $row++) {
            $parsed = $this->parseRow($worksheet, $row);
            if ($parsed) {
                $transactions->push($parsed);
            }
        }

        return $transactions;
    }

    /**
     * Parse a single row from the worksheet
     */
    protected function parseRow($worksheet, int $row): ?array
    {
        $dateValue = $worksheet->getCell(self::COL_DATE . $row)->getValue();

        // Skip empty rows
        if (empty($dateValue)) {
            return null;
        }

        $remitter = trim($worksheet->getCell(self::COL_REMITTER . $row)->getValue() ?? '');
        $remitterBank = trim($worksheet->getCell(self::COL_REMITTER_BANK . $row)->getValue() ?? '');
        $description = trim($worksheet->getCell(self::COL_DESCRIPTION . $row)->getValue() ?? '');
        $transactionNo = trim($worksheet->getCell(self::COL_TRANSACTION_NO . $row)->getValue() ?? '');
        $debitValue = $worksheet->getCell(self::COL_DEBIT . $row)->getValue();
        $creditValue = $worksheet->getCell(self::COL_CREDIT . $row)->getValue();
        $balanceValue = $worksheet->getCell(self::COL_BALANCE . $row)->getValue();

        // Parse amounts (remove commas)
        $debitAmount = $this->parseAmount($debitValue);
        $creditAmount = $this->parseAmount($creditValue);
        $balance = $this->parseAmount($balanceValue);

        // Determine transaction type
        if ($creditAmount > 0) {
            $type = 'income';
            $amount = $creditAmount;
        } elseif ($debitAmount > 0) {
            $type = 'expense';
            $amount = $debitAmount;
        } else {
            // Skip zero or invalid amounts
            return null;
        }

        // Parse date (DD/MM/YYYY format)
        $transactionDate = $this->parseDate($dateValue);
        if (! $transactionDate) {
            return null;
        }

        // Build full description
        $fullDescription = $this->buildDescription($description, $remitter, $remitterBank);

        // Suggest category
        $suggestedCategory = $this->suggestCategory($fullDescription);

        return [
            'currency' => 'VND',
            'transaction_date' => $transactionDate->format('Y-m-d'),
            'transaction_time' => null,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'type' => $type,
            'amount' => $amount,
            'description' => $fullDescription,
            'running_balance' => $balance,
            'suggested_category' => $suggestedCategory,
            'is_transfer' => $this->isTransfer($fullDescription),
            'transaction_no' => $transactionNo,
            'remitter' => $remitter,
            'remitter_bank' => $remitterBank,
            'original_description' => $description,
        ];
    }

    /**
     * Parse amount string to float
     */
    protected function parseAmount($value): float
    {
        if (empty($value)) {
            return 0.0;
        }

        // Remove commas and spaces
        $cleaned = str_replace([',', ' '], '', (string) $value);

        return abs((float) $cleaned);
    }

    /**
     * Parse date from DD/MM/YYYY format
     */
    protected function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            // Handle Excel date serial number
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp(
                    ($value - 25569) * 86400
                );
            }

            // Handle DD/MM/YYYY string format
            return Carbon::createFromFormat('d/m/Y', trim($value));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Build full description from parts
     */
    protected function buildDescription(string $description, string $remitter, string $remitterBank): string
    {
        $parts = array_filter([$description, $remitter, $remitterBank]);

        return implode(' - ', $parts) ?: 'No description';
    }

    /**
     * Check if transaction is a transfer
     */
    protected function isTransfer(string $description): bool
    {
        $descLower = strtolower($description);

        return str_contains($descLower, 'chuyen tien') ||
            str_contains($descLower, 'transfer') ||
            str_contains($descLower, 'chuyen khoan');
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
                    Transaction::create([
                        'user_id' => $userId,
                        'account_id' => $account->id,
                        'category_id' => $categoryId,
                        'transaction_type' => $tx['type'],
                        'amount' => $tx['amount'],
                        'currency_code' => $account->currency_code,
                        'description' => $tx['description'],
                        'notes' => "Imported from Techcombank (Ref: {$tx['transaction_no']})",
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
