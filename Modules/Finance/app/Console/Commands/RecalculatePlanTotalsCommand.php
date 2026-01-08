<?php

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\Models\PlanPeriod;

class RecalculatePlanTotalsCommand extends Command
{
    protected $signature = 'finance:recalculate-plan-totals';

    protected $description = 'Recalculate plan period totals based on item recurrence';

    public function handle(): int
    {
        $this->info('Recalculating plan period totals...');

        $periods = PlanPeriod::with('items')->get();

        if ($periods->isEmpty()) {
            $this->comment('No plan periods found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($periods->count());
        $bar->start();

        foreach ($periods as $period) {
            $oldIncome = $period->planned_income;
            $oldExpense = $period->planned_expense;

            $period->recalculateTotals();

            if ($oldIncome != $period->planned_income || $oldExpense != $period->planned_expense) {
                $this->newLine();
                $this->line("Period {$period->year}: Income {$oldIncome} → {$period->planned_income}, Expense {$oldExpense} → {$period->planned_expense}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Recalculated {$periods->count()} plan periods.");

        return self::SUCCESS;
    }
}
