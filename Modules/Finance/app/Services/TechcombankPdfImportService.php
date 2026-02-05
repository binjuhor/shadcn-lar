<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\{Account, Category, Transaction};
use Smalot\PdfParser\Parser;

class TechcombankPdfImportService
{
    protected array $categoryMappings = [
        'chuyen tien' => 'Transfer',
        'transfer' => 'Transfer',
        'luong' => 'Salary',
        'salary' => 'Salary',
        'interest' => 'Investment Income',
        'lai suat' => 'Investment Income',
        'loi nhuan' => 'Investment Income',
        'sinh loi' => 'Investment Income',
        'tikop' => 'Investment Income',
        'CCTG' => 'Investment Income',
        'Bao Loc' => 'Investment Income',
        'dien' => 'Utilities',
        'nuoc' => 'Utilities',
        'internet' => 'Utilities',
        'fpt' => 'Utilities',
        'viettel' => 'Utilities',
        'vnpt' => 'Utilities',
        'nap tien' => 'Utilities',
        'grab' => 'Food & Dining',
        'shopee' => 'Shopping',
        'lazada' => 'Shopping',
        'tiki' => 'Shopping',
        'affiliate' => 'Affiliate Income',
        'phi' => 'Bank Fees',
        'fee' => 'Bank Fees',
        'benh vien' => 'Healthcare',
        'no vay' => 'Loan Payment',
        'thanh toan' => 'Other Expenses',
    ];

    /**
     * Parse Techcombank PDF and return structured transaction data
     * Converts PDF to CSV first, then parses from CSV for accurate debit/credit detection
     */
    public function parsePdf(string $filePath): Collection
    {
        // Step 1: Convert PDF to CSV and store temporarily
        $csvPath = $this->convertToCsv($filePath);

        // Step 2: Parse the CSV with proper debit/credit column detection
        $transactions = $this->parseCsvFile($csvPath);

        // Step 3: Clean up temp CSV file
        Storage::delete(str_replace(Storage::path(''), '', $csvPath));

        return $transactions;
    }

    /**
     * Parse CSV file with Debit/Credit columns for accurate type detection
     */
    protected function parseCsvFile(string $csvPath): Collection
    {
        $transactions = collect();

        if (($handle = fopen($csvPath, 'r')) === false) {
            return $transactions;
        }

        // Skip BOM if present and read header
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return $transactions;
        }

        // Normalize headers
        $headers = array_map(fn ($h) => strtolower(trim($h ?? '')), $headers);

        // Map columns
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

            $debit = $this->parseAmountValue($row[$debitCol] ?? '');
            $credit = $this->parseAmountValue($row[$creditCol] ?? '');

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $description = trim($row[$descCol] ?? '');
            $txNo = trim($row[$txNoCol] ?? '');
            $remitter = trim($row[$remitterCol] ?? '');
            $bank = trim($row[$bankCol] ?? '');

            // Build full description with transaction number
            $fullDesc = $description ?: 'Transaction';
            if ($txNo) {
                $fullDesc .= " (Ref: {$txNo})";
            }

            $transactionDate = $this->parseDate($date);
            if (! $transactionDate) {
                continue;
            }

            // Determine type from Debit/Credit columns
            $type = $credit > 0 ? 'income' : 'expense';
            $amount = $credit > 0 ? $credit : $debit;

            $transactions->push([
                'currency' => 'VND',
                'transaction_date' => $transactionDate->format('Y-m-d'),
                'transaction_time' => null,
                'timezone' => 'Asia/Ho_Chi_Minh',
                'type' => $type,
                'amount' => $amount,
                'description' => $fullDesc,
                'running_balance' => $this->parseAmountValue($row[$balanceCol] ?? ''),
                'suggested_category' => $this->suggestCategory($description),
                'is_transfer' => $this->isTransfer($description),
                'transaction_no' => $txNo,
                'remitter' => $remitter,
                'remitter_bank' => $bank ?: 'TECHCOMBANK',
                'original_description' => $description,
            ]);
        }

        fclose($handle);

        return $transactions->values();
    }

    protected function parseAmountValue(string $value): float
    {
        if (empty($value)) {
            return 0.0;
        }

        $cleaned = preg_replace('/[^\d.,\-]/', '', $value);
        $cleaned = str_replace(',', '', $cleaned);

        return abs((float) $cleaned);
    }

    /**
     * Convert PDF to CSV and store in storage
     */
    public function convertToCsv(string $pdfPath): string
    {
        $transactions = $this->parsePdfRaw($pdfPath);

        $filename = 'imports/techcombank_'.now()->format('YmdHis').'_'.uniqid().'.csv';

        $csvContent = $this->generateCsvContent($transactions);
        Storage::put($filename, $csvContent);

        return Storage::path($filename);
    }

    /**
     * Parse PDF and return raw transaction data (before formatting)
     */
    protected function parsePdfRaw(string $filePath): Collection
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);

        $allTransactions = collect();

        foreach ($pdf->getPages() as $pageNum => $page) {
            $text = $page->getText();
            $transactions = $this->parsePageTransactions($text, $pageNum + 1);
            $allTransactions = $allTransactions->merge($transactions);
        }

        return $allTransactions;
    }

    protected function parsePageTransactions(string $text, int $pageNum): Collection
    {
        $transactions = collect();

        // Extract all dates (DD/MM/YYYY)
        preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $text, $dateMatches);
        $dates = array_filter($dateMatches[0], fn ($d) => $this->isValidDate($d));
        $dates = array_values(array_unique($dates));

        // Extract FT numbers with full description text attached
        // Pattern: FT + 11+ digits + optional backslash code + description (until next FT or large number)
        preg_match_all('/(FT\d{11,})((?:\\\\[A-Z]{2,3})?.{0,200}?)(?=FT\d{11}|\s\d{1,3}(?:,\d{3})+\.\d{2}|$)/s', $text, $ftMatches, PREG_SET_ORDER);

        // Extract all amounts (numbers with comma and .00)
        preg_match_all('/\s(-?[\d,]+\.\d{2})/', $text, $amountMatches);
        $amounts = array_map(fn ($a) => (float) str_replace(',', '', $a), $amountMatches[1]);

        // Skip opening balance on page 1
        $openingBalanceIndex = 0;
        if ($pageNum === 1 && ! empty($amounts)) {
            if (stripos($text, 'Opening balance') !== false || stripos($text, 'Số dư đầu kỳ') !== false) {
                $openingBalanceIndex = 1;
            }
        }

        $dateIndex = 0;
        $amountIndex = $openingBalanceIndex;

        foreach ($ftMatches as $index => $match) {
            $ftNumber = $match[1];
            $attachedText = $match[2] ?? '';

            $description = $this->cleanDescription($attachedText);
            $date = $dates[$dateIndex] ?? ($dates[0] ?? date('d/m/Y'));

            $debit = 0;
            $credit = 0;
            $balance = 0;

            if (isset($amounts[$amountIndex])) {
                $amount1 = $amounts[$amountIndex];
                $amount2 = $amounts[$amountIndex + 1] ?? 0;

                if ($this->isLikelyCredit($description)) {
                    $credit = $amount1;
                    $balance = $amount2;
                } else {
                    $debit = $amount1;
                    $balance = $amount2;
                }

                $amountIndex += 2;
            }

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            // Increment date index periodically
            if (($index + 1) % 3 == 0 && $dateIndex < count($dates) - 1) {
                $dateIndex++;
            }

            // Include transaction number in description
            $fullDescription = $description ?: 'Transaction';
            $fullDescription .= " (Ref: {$ftNumber})";

            $transactions->push([
                'date' => $date,
                'remitter' => '',
                'remitter_bank' => 'TECHCOMBANK',
                'description' => $fullDescription,
                'transaction_no' => $ftNumber,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ]);
        }

        return $transactions;
    }

    protected function generateCsvContent(Collection $transactions): string
    {
        $output = fopen('php://temp', 'r+');

        // BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header
        fputcsv($output, [
            'Date',
            'Remitter',
            'Remitter Bank',
            'Description',
            'Transaction No',
            'Debit',
            'Credit',
            'Balance',
        ]);

        foreach ($transactions as $tx) {
            fputcsv($output, [
                $tx['date'],
                $tx['remitter'],
                $tx['remitter_bank'],
                $tx['description'],
                $tx['transaction_no'],
                $tx['debit'] > 0 ? number_format($tx['debit'], 2, '.', '') : '',
                $tx['credit'] > 0 ? number_format($tx['credit'], 2, '.', '') : '',
                $tx['balance'] > 0 ? number_format($tx['balance'], 2, '.', '') : '',
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    protected function isValidDate(string $date): bool
    {
        try {
            $parsed = Carbon::createFromFormat('d/m/Y', $date);

            return $parsed->year >= 2020 && $parsed->year <= 2030;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function parseDate(string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value);
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return Carbon::createFromFormat('Y-m-d', $value);
            }

            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function cleanDescription(string $text): string
    {
        // Remove backslash prefixes like \BNK, \TLG, \BKB at the start
        $text = preg_replace('/^\\\\(BNK|BKB|TLG)/i', '', $text);

        // Remove trailing backslash patterns
        $text = preg_replace('/\\\\(BNK|BKB|TLG)$/i', '', $text);

        // Add spaces before capital letters (for concatenated text)
        $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text);

        // Clean up multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);

        $text = trim($text);

        // Format Vietnamese transfer keywords
        $text = preg_replace('/chuyen\s*ti\s*en/i', 'chuyen tien', $text);
        $text = preg_replace('/chuyen\s*khoan/i', 'chuyen khoan', $text);

        return $text;
    }

    protected function isLikelyCredit(string $description): bool
    {
        $descLower = strtolower($description);

        // Explicit EXPENSE keywords - check first
        $expenseKeywords = [
            'nap tien',           // top-up (spending)
            'thanh toan no vay',  // loan payment
            't.toan qr',          // QR payment
            'thanh toan qr',      // QR payment
            'mua hang',           // purchase
        ];

        foreach ($expenseKeywords as $keyword) {
            if (str_contains($descLower, $keyword)) {
                return false;
            }
        }

        // Explicit INCOME keywords
        $creditKeywords = [
            'rut tien tu tikop',  // withdraw from Tikop savings
            'sinh loi tu dong',   // automatic profit
            'loi nhuan',          // profit
            'cctg bao loc',       // CCTG investment
            'phan bo so du',      // balance distribution
            'affiliate',          // affiliate income
            'lai suat',           // interest
        ];

        foreach ($creditKeywords as $keyword) {
            if (str_contains($descLower, $keyword)) {
                return true;
            }
        }

        // Default to expense (debit) - safer assumption
        return false;
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
                        'notes' => "Imported from Techcombank PDF (Ref: {$tx['transaction_no']})",
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
