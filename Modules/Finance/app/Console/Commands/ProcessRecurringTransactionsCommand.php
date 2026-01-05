<?php

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\Services\RecurringTransactionService;

class ProcessRecurringTransactionsCommand extends Command
{
    protected $signature = 'finance:process-recurring';

    protected $description = 'Process all due recurring transactions';

    public function handle(RecurringTransactionService $service): int
    {
        $this->info('Processing recurring transactions...');

        $result = $service->processDue();

        $this->info("Processed {$result['processed']} recurring transaction(s).");

        if (! empty($result['errors'])) {
            $this->warn('Errors occurred:');
            foreach ($result['errors'] as $id => $error) {
                $this->error("  - ID {$id}: {$error}");
            }
        }

        return Command::SUCCESS;
    }
}
