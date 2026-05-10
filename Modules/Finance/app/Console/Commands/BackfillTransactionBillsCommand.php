<?php

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\Models\SmartInputHistory;
use Modules\Finance\Services\BillAttachmentService;

/**
 * One-shot backfill: copies receipt images attached to historical SmartInputHistory
 * records onto the linked Transaction's `bills` collection, so users can find their
 * bills from the transaction list (not only the smart-input history).
 *
 * Idempotent: skips transactions that already have any media in `bills` to avoid
 * creating duplicates on re-run.
 */
class BackfillTransactionBillsCommand extends Command
{
    protected $signature = 'finance:backfill-transaction-bills
                            {--dry-run : Show what would be copied without copying}';

    protected $description = 'Copy receipt attachments from SmartInputHistory to their linked Transaction';

    public function handle(BillAttachmentService $billAttachmentService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $histories = SmartInputHistory::with(['media', 'transaction.media'])
            ->whereNotNull('transaction_id')
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'input_attachments'))
            ->get();

        if ($histories->isEmpty()) {
            $this->info('No smart-input histories with attachments found.');

            return self::SUCCESS;
        }

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($histories as $history) {
            $transaction = $history->transaction;

            if (! $transaction) {
                $skipped++;
                continue;
            }

            if ($transaction->getMedia('bills')->isNotEmpty()) {
                $this->line("Skipping history #{$history->id} → tx #{$transaction->id} (already has bills)");
                $skipped++;
                continue;
            }

            $attachmentCount = $history->getMedia('input_attachments')->count();
            $this->line("History #{$history->id} → tx #{$transaction->id}: copying {$attachmentCount} attachment(s)");

            if ($dryRun) {
                $copied += $attachmentCount;
                continue;
            }

            try {
                $billAttachmentService->copyCollection($history, 'input_attachments', $transaction, 'bills');
                $copied += $attachmentCount;
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
                report($e);
                $failed++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY-RUN] Would copy' : 'Copied').": {$copied} file(s)");
        $this->info("Skipped: {$skipped} history record(s)");
        if ($failed > 0) {
            $this->warn("Failed: {$failed} record(s) — see logs");
        }

        return self::SUCCESS;
    }
}
