<?php

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\Models\{
    Account,
    AdvisorConversation,
    RecurringTransaction,
    SavingsContribution,
    SavingsGoal,
    SmartInputHistory,
    Transaction
};

class PurgeSoftDeletedRecordsCommand extends Command
{
    protected $signature = 'finance:purge-deleted
                            {--type= : Type to purge (transactions, accounts, savings-goals, savings-contributions, recurring, conversations, smart-input)}
                            {--all : Purge all soft-deleted records}
                            {--days=30 : Only purge records deleted more than N days ago}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Permanently delete soft-deleted Finance records';

    /** @var array<string, class-string> */
    protected array $models = [
        'transactions' => Transaction::class,
        'accounts' => Account::class,
        'savings-goals' => SavingsGoal::class,
        'savings-contributions' => SavingsContribution::class,
        'recurring' => RecurringTransaction::class,
        'conversations' => AdvisorConversation::class,
        'smart-input' => SmartInputHistory::class,
    ];

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $purgeAll = $this->option('all');
        $type = $this->option('type');

        if (! $purgeAll && ! $type) {
            $this->error('Please specify --type=<type> or --all');
            $this->line('');
            $this->info('Available types:');
            foreach (array_keys($this->models) as $key) {
                $this->line("  - {$key}");
            }

            return self::FAILURE;
        }

        if ($type && ! isset($this->models[$type])) {
            $this->error("Unknown type: {$type}");
            $this->info('Available types: ' . implode(', ', array_keys($this->models)));

            return self::FAILURE;
        }

        $targets = $purgeAll ? $this->models : [$type => $this->models[$type]];
        $cutoff = now()->subDays($days);
        $totalDeleted = 0;

        $this->info($dryRun ? 'DRY RUN — no records will be deleted' : 'Purging soft-deleted records...');
        $this->line("Cutoff: deleted before {$cutoff->toDateTimeString()} ({$days} days ago)");
        $this->line('');

        foreach ($targets as $key => $modelClass) {
            $count = $modelClass::onlyTrashed()
                ->where('deleted_at', '<', $cutoff)
                ->count();

            if ($count === 0) {
                $this->line("  {$key}: no records to purge");
                continue;
            }

            if ($dryRun) {
                $this->warn("  {$key}: {$count} records would be deleted");
            } else {
                $modelClass::onlyTrashed()
                    ->where('deleted_at', '<', $cutoff)
                    ->forceDelete();
                $this->info("  {$key}: {$count} records permanently deleted");
            }

            $totalDeleted += $count;
        }

        $this->line('');
        $action = $dryRun ? 'would be purged' : 'purged';
        $this->comment("Total: {$totalDeleted} records {$action}.");

        return self::SUCCESS;
    }
}
