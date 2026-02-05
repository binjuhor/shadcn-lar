<?php

namespace Modules\Finance\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser;

class ConvertTechcombankPdfCommand extends Command
{
    protected $signature = 'finance:convert-tcb-pdf {input : Path to PDF file} {output? : Path to output CSV file}';

    protected $description = 'Convert Techcombank PDF statement to CSV for import';

    public function handle(): int
    {
        $inputPath = $this->argument('input');
        $outputPath = $this->argument('output');

        if (! file_exists($inputPath)) {
            $this->error("File not found: {$inputPath}");

            return 1;
        }

        if (! $outputPath) {
            $outputPath = preg_replace('/\.pdf$/i', '.csv', $inputPath);
        }

        $this->info("Parsing PDF: {$inputPath}");

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($inputPath);

            $allTransactions = collect();

            foreach ($pdf->getPages() as $pageNum => $page) {
                $text = $page->getText();
                $transactions = $this->parsePageTransactions($text, $pageNum + 1);
                $allTransactions = $allTransactions->merge($transactions);
            }

            $this->info("Found {$allTransactions->count()} transactions");

            if ($allTransactions->isEmpty()) {
                $this->warn('No transactions found. The PDF format might not be supported.');

                return 1;
            }

            $this->writeCsv($outputPath, $allTransactions);

            $this->info("CSV saved to: {$outputPath}");
            $this->newLine();
            $this->table(
                ['Date', 'Type', 'Amount', 'Description'],
                $allTransactions->take(5)->map(fn ($t) => [
                    $t['date'],
                    $t['debit'] > 0 ? 'Debit' : 'Credit',
                    number_format($t['debit'] > 0 ? $t['debit'] : $t['credit'], 0),
                    mb_substr($t['description'], 0, 40).'...',
                ])->toArray()
            );

            if ($allTransactions->count() > 5) {
                $this->info('... and '.($allTransactions->count() - 5).' more transactions');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    protected function parsePageTransactions(string $text, int $pageNum): \Illuminate\Support\Collection
    {
        $transactions = collect();

        // Extract all dates (DD/MM/YYYY)
        preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $text, $dateMatches);
        $dates = array_filter($dateMatches[0], fn ($d) => $this->isValidDate($d));
        $dates = array_values(array_unique($dates));

        // Extract all FT transaction numbers with their attached text
        preg_match_all('/(FT\d{11,})([^\s]*)/i', $text, $ftMatches, PREG_SET_ORDER);

        // Extract all amounts (numbers with comma and .00)
        preg_match_all('/\s(-?[\d,]+\.\d{2})/', $text, $amountMatches);
        $amounts = array_map(fn ($a) => (float) str_replace(',', '', $a), $amountMatches[1]);

        // Skip opening balance (usually first amount on page 1)
        $openingBalanceIndex = 0;
        if ($pageNum === 1 && ! empty($amounts)) {
            // Find the position of "Opening balance" text
            if (stripos($text, 'Opening balance') !== false || stripos($text, 'Số dư đầu kỳ') !== false) {
                $openingBalanceIndex = 1;
            }
        }

        // Process FT transactions
        $dateIndex = 0;
        $amountIndex = $openingBalanceIndex;

        foreach ($ftMatches as $index => $match) {
            $ftNumber = $match[1];
            $attachedText = $match[2] ?? '';

            // Clean up FT number (remove attached text for the number itself)
            $ftNumberClean = $ftNumber;

            // Get description from attached text
            $description = $this->cleanDescription($attachedText);

            // Determine the date for this transaction
            // Usually transactions are listed in date order
            $date = $dates[$dateIndex] ?? ($dates[0] ?? date('d/m/Y'));

            // Get amounts - pattern is usually: debit/credit amount, then balance
            $debit = 0;
            $credit = 0;
            $balance = 0;

            if (isset($amounts[$amountIndex])) {
                $amount1 = $amounts[$amountIndex];
                $amount2 = $amounts[$amountIndex + 1] ?? 0;

                // Determine if debit or credit based on description
                if ($this->isLikelyCredit($description)) {
                    $credit = $amount1;
                    $balance = $amount2;
                } else {
                    $debit = $amount1;
                    $balance = $amount2;
                }

                $amountIndex += 2;
            }

            // Skip if no valid amount
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            // Increment date index periodically
            // This is a heuristic - dates in the PDF are often grouped
            if (($index + 1) % 3 == 0 && $dateIndex < count($dates) - 1) {
                $dateIndex++;
            }

            $transactions->push([
                'date' => $date,
                'remitter' => '',
                'remitter_bank' => 'TECHCOMBANK',
                'description' => $description ?: 'Transaction',
                'transaction_no' => $ftNumberClean,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ]);
        }

        return $transactions;
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

    protected function cleanDescription(string $text): string
    {
        // Remove common suffixes
        $text = preg_replace('/\\\\BNK$|\\\\BKB$|\\\\TLG$/i', '', $text);

        // Add spaces before capital letters
        $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text);

        // Clean up
        $text = trim($text);

        // If starts with common patterns, format nicely
        if (preg_match('/^(HOANG|NGUYEN|PHAM|TRAN|LE|VU)/i', $text)) {
            // It's a name, likely a transfer
            $text = preg_replace('/chuyen\s*ti\s*en/i', 'chuyen tien', $text);
        }

        return $text;
    }

    protected function isLikelyCredit(string $description): bool
    {
        $creditKeywords = [
            'nhan', 'receive', 'lai', 'interest', 'loi nhuan', 'hoan',
            'sinh loi', 'Bao Loc', 'CCTG', 'phan bo', 'Tikop', 'Rut tien tu',
            'PHAM VAN HIEN chuyen', // specific incoming transfer
        ];

        $descLower = strtolower($description);
        foreach ($creditKeywords as $keyword) {
            if (str_contains($descLower, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    protected function writeCsv(string $path, \Illuminate\Support\Collection $transactions): void
    {
        $handle = fopen($path, 'w');

        // BOM for UTF-8
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header
        fputcsv($handle, [
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
            fputcsv($handle, [
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

        fclose($handle);
    }
}
