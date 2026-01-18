<?php

namespace Modules\Finance\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\{Account, Transaction};
use PhpOffice\PhpSpreadsheet\{IOFactory, Shared\Date};

class ImportTransactionsCommand extends Command
{
    protected $signature = 'finance:import-transactions
                            {file : Path to the Excel file}
                            {--account= : Account name or ID to import to}
                            {--user= : User ID (defaults to account owner)}
                            {--dry-run : Preview without importing}
                            {--skip-duplicates : Skip transactions that already exist}';

    protected $description = 'Import transactions from Excel file (monthly sheets format)';

    protected int $imported = 0;

    protected int $skipped = 0;

    protected int $errors = 0;

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $filePath = str_replace('~', getenv('HOME'), $filePath);
        }

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $account = $this->resolveAccount();
        if (! $account) {
            return self::FAILURE;
        }

        $userId = $this->option('user') ?? $account->user_id;
        $isDryRun = $this->option('dry-run');
        $skipDuplicates = $this->option('skip-duplicates');

        $this->info("Importing transactions to: {$account->name} (ID: {$account->id})");
        $this->info("User ID: {$userId}");
        $this->info("Currency: {$account->currency_code}");

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheetNames = $spreadsheet->getSheetNames();

        $this->info("Found {$spreadsheet->getSheetCount()} sheets");
        $this->newLine();

        $allTransactions = [];

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $transactions = $this->parseSheet($sheet, $sheetName);
            $allTransactions = array_merge($allTransactions, $transactions);
        }

        $this->info('Total transactions found: '.count($allTransactions));

        if (empty($allTransactions)) {
            $this->warn('No transactions to import');

            return self::SUCCESS;
        }

        // Sort by date
        usort($allTransactions, fn ($a, $b) => $a['date'] <=> $b['date']);

        if (! $isDryRun) {
            $this->importTransactions($allTransactions, $account, $userId, $skipDuplicates);
        } else {
            $this->previewTransactions($allTransactions);
        }

        $this->newLine();
        $this->info("Imported: {$this->imported}");
        $this->info("Skipped: {$this->skipped}");
        if ($this->errors > 0) {
            $this->error("Errors: {$this->errors}");
        }

        return self::SUCCESS;
    }

    protected function resolveAccount(): ?Account
    {
        $accountOption = $this->option('account');

        if (! $accountOption) {
            $this->error('Please specify an account with --account');

            return null;
        }

        if (is_numeric($accountOption)) {
            $account = Account::find($accountOption);
        } else {
            $account = Account::where('name', 'like', "%{$accountOption}%")->first();
        }

        if (! $account) {
            $this->error("Account not found: {$accountOption}");

            return null;
        }

        return $account;
    }

    protected function parseSheet($sheet, string $sheetName): array
    {
        $transactions = [];
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        if ($highestRow < 2) {
            return [];
        }

        // Find header row (row with "Ngày")
        $headerRowNum = null;
        $columns = [];
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($row = 1; $row <= min(5, $highestRow); $row++) {
            for ($colIdx = 1; $colIdx <= $highestColumnIndex; $colIdx++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $value = $sheet->getCell($col.$row)->getValue();
                if (is_string($value) && strtolower(trim($value)) === 'ngày') {
                    $headerRowNum = $row;
                    break 2;
                }
            }
        }

        if (! $headerRowNum) {
            $this->warn("Could not find header in sheet: {$sheetName}");

            return [];
        }

        // Map columns from header row
        for ($colIdx = 1; $colIdx <= $highestColumnIndex; $colIdx++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $value = strtolower(trim((string) $sheet->getCell($col.$headerRowNum)->getValue()));
            if ($value === 'ngày') {
                $columns['date'] = $col;
            } elseif (str_contains($value, 'tên') || str_contains($value, 'món') || str_contains($value, 'dự án')) {
                $columns['description'] = $col;
            } elseif ($value === 'tiền' || str_contains($value, 'thu về')) {
                $columns['amount'] = $col;
            } elseif ($value === 'tag') {
                $columns['tag'] = $col;
            }
        }

        if (! isset($columns['date']) || ! isset($columns['amount'])) {
            $this->warn("Could not determine required columns in sheet: {$sheetName} (found: ".implode(', ', array_keys($columns)).')');

            return [];
        }

        // Parse data rows
        for ($row = $headerRowNum + 1; $row <= $highestRow; $row++) {
            $dateValue = $sheet->getCell($columns['date'].$row)->getValue();
            $amountValue = $sheet->getCell($columns['amount'].$row)->getValue();
            $description = isset($columns['description']) ? (string) $sheet->getCell($columns['description'].$row)->getValue() : '';
            $tag = isset($columns['tag']) ? (string) $sheet->getCell($columns['tag'].$row)->getValue() : '';

            // Skip empty rows
            if ($amountValue === null || $amountValue === '' || ! is_numeric($amountValue)) {
                continue;
            }

            // Parse date
            $date = $this->parseDate($dateValue, $sheetName);
            if (! $date) {
                continue;
            }

            $amount = (float) $amountValue;

            $transactions[] = [
                'date' => $date,
                'description' => trim($description),
                'amount' => abs($amount),
                'type' => $amount >= 0 ? 'income' : 'expense',
                'tag' => trim($tag),
                'sheet' => $sheetName,
            ];
        }

        return $transactions;
    }

    protected function parseDate($value, string $sheetName): ?Carbon
    {
        if (empty($value)) {
            // Try to extract from sheet name (e.g., "Tháng 5 2022")
            if (preg_match('/(\d{1,2})\s*(\d{4})/', $sheetName, $matches)) {
                return Carbon::create($matches[2], $matches[1], 1);
            }

            return null;
        }

        // Excel serial date
        if (is_numeric($value)) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject($value));
            } catch (\Exception $e) {
                return null;
            }
        }

        // String date
        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    protected function importTransactions(array $transactions, Account $account, int $userId, bool $skipDuplicates): void
    {
        $bar = $this->output->createProgressBar(count($transactions));
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($transactions as $txn) {
                if ($skipDuplicates && $this->isDuplicate($txn, $account->id)) {
                    $this->skipped++;
                    $bar->advance();

                    continue;
                }

                Transaction::create([
                    'account_id' => $account->id,
                    'user_id' => $userId,
                    'transaction_type' => $txn['type'],
                    'amount' => (int) round($txn['amount']),
                    'currency_code' => $account->currency_code,
                    'transaction_date' => $txn['date'],
                    'description' => $txn['description'] ?: null,
                    'notes' => $txn['tag'] ? "Tag: {$txn['tag']}" : null,
                    'is_reconciled' => true,
                ]);

                // Update account balance
                if ($txn['type'] === 'income') {
                    $account->increment('current_balance', (int) round($txn['amount']));
                } else {
                    $account->decrement('current_balance', (int) round($txn['amount']));
                }

                $this->imported++;
                $bar->advance();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errors++;
            $this->error("Import failed: {$e->getMessage()}");
        }

        $bar->finish();
        $this->newLine();
    }

    protected function isDuplicate(array $txn, int $accountId): bool
    {
        return Transaction::where('account_id', $accountId)
            ->whereDate('transaction_date', $txn['date'])
            ->where('amount', (int) round($txn['amount']))
            ->where('transaction_type', $txn['type'])
            ->where('description', $txn['description'] ?: null)
            ->exists();
    }

    protected function previewTransactions(array $transactions): void
    {
        $preview = array_slice($transactions, 0, 20);

        $this->table(
            ['Date', 'Type', 'Amount', 'Description', 'Tag'],
            array_map(fn ($t) => [
                $t['date']->format('Y-m-d'),
                $t['type'],
                number_format($t['amount']),
                substr($t['description'], 0, 40),
                $t['tag'],
            ], $preview)
        );

        if (count($transactions) > 20) {
            $this->info('... and '.(count($transactions) - 20).' more');
        }

        $this->skipped = count($transactions);
    }
}
