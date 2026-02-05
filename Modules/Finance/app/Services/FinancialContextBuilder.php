<?php

namespace Modules\Finance\Services;

use Modules\Finance\Models\{
    Account,
    Budget,
    FinancialPlan,
    RecurringTransaction,
    SavingsGoal,
    Transaction
};

class FinancialContextBuilder
{
    public function build(int $userId): string
    {
        $sections = array_filter([
            $this->buildAccountsSummary($userId),
            $this->buildRecentTransactionSummary($userId),
            $this->buildBudgetsSummary($userId),
            $this->buildSavingsGoalsSummary($userId),
            $this->buildRecurringSummary($userId),
            $this->buildFinancialPlansSummary($userId),
            $this->buildIncomeExpenseTrend($userId),
        ]);

        if (empty($sections)) {
            return "No financial data available yet.";
        }

        return implode("\n\n", $sections);
    }

    protected function buildAccountsSummary(int $userId): string
    {
        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($accounts->isEmpty()) {
            return '';
        }

        $lines = ["## Accounts"];
        $totalAssets = 0;
        $totalLiabilities = 0;

        foreach ($accounts as $account) {
            $balance = $account->current_balance;
            $type = $account->account_type;
            $lines[] = "- {$account->name} ({$type}, {$account->currency_code}): {$balance}";

            if ($account->has_credit_limit) {
                $totalLiabilities += $account->amount_owed;
            } else {
                $totalAssets += max(0, $balance);
            }
        }

        $netWorth = $totalAssets - $totalLiabilities;
        $lines[] = "Total assets: {$totalAssets} | Liabilities: {$totalLiabilities} | Net worth: {$netWorth}";

        return implode("\n", $lines);
    }

    protected function buildRecentTransactionSummary(int $userId): string
    {
        $since = now()->subDays(30);

        $summary = Transaction::where('user_id', $userId)
            ->where('transaction_date', '>=', $since)
            ->selectRaw("transaction_type, SUM(amount) as total, COUNT(*) as count")
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');

        if ($summary->isEmpty()) {
            return '';
        }

        $income = $summary->get('income');
        $expense = $summary->get('expense');

        $incomeTotal = $income ? $income->total : 0;
        $expenseTotal = $expense ? $expense->total : 0;
        $incomeCount = $income?->count ?? 0;
        $expenseCount = $expense?->count ?? 0;
        $net = $incomeTotal - $expenseTotal;

        $lines = ["## Last 30 Days"];
        $lines[] = "Income: {$incomeTotal} ({$incomeCount} transactions)";
        $lines[] = "Expenses: {$expenseTotal} ({$expenseCount} transactions)";
        $lines[] = "Net: {$net}";

        // Top expense categories
        $topCategories = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'expense')
            ->where('transaction_date', '>=', $since)
            ->join('finance_categories', 'finance_transactions.category_id', '=', 'finance_categories.id')
            ->selectRaw('finance_categories.id, finance_categories.name, SUM(finance_transactions.amount) as total')
            ->groupBy('finance_categories.id', 'finance_categories.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($topCategories->isNotEmpty()) {
            $lines[] = "Top expense categories: " . $topCategories->map(
                fn ($c) => "{$c->name}: {$c->total}"
            )->implode(', ');
        }

        return implode("\n", $lines);
    }

    protected function buildBudgetsSummary(int $userId): string
    {
        $budgets = Budget::where('user_id', $userId)
            ->where('is_active', true)
            ->where('end_date', '>=', now())
            ->with('category')
            ->get();

        if ($budgets->isEmpty()) {
            return '';
        }

        $lines = ["## Active Budgets"];

        foreach ($budgets as $budget) {
            $pct = $budget->getSpentPercent();
            $status = $budget->isOverBudget() ? 'OVER BUDGET' : round($pct) . '% used';
            $categoryName = $budget->category?->name ?? 'General';
            $lines[] = "- {$budget->name} ({$categoryName}): {$budget->spent_amount}/{$budget->allocated_amount} ({$status})";
        }

        return implode("\n", $lines);
    }

    protected function buildSavingsGoalsSummary(int $userId): string
    {
        $goals = SavingsGoal::where('user_id', $userId)
            ->active()
            ->get();

        if ($goals->isEmpty()) {
            return '';
        }

        $lines = ["## Savings Goals"];

        foreach ($goals as $goal) {
            $pct = round($goal->progress_percent);
            $remaining = $goal->remaining_amount;
            $targetDate = $goal->target_date ? $goal->target_date->format('Y-m-d') : 'no deadline';
            $lines[] = "- {$goal->name}: {$goal->current_amount}/{$goal->target_amount} ({$pct}%, remaining: {$remaining}, target: {$targetDate})";
        }

        return implode("\n", $lines);
    }

    protected function buildRecurringSummary(int $userId): string
    {
        $recurring = RecurringTransaction::where('user_id', $userId)
            ->active()
            ->get();

        if ($recurring->isEmpty()) {
            return '';
        }

        $monthlyIncome = 0;
        $monthlyExpense = 0;

        foreach ($recurring as $item) {
            if ($item->transaction_type === 'income') {
                $monthlyIncome += $item->monthly_amount;
            } else {
                $monthlyExpense += $item->monthly_amount;
            }
        }

        $net = $monthlyIncome - $monthlyExpense;

        $lines = ["## Recurring (Monthly Projection)"];
        $lines[] = "Projected income: {$monthlyIncome} | Projected expenses: {$monthlyExpense} | Net: {$net}";
        $lines[] = "Active recurring items: {$recurring->count()}";

        return implode("\n", $lines);
    }

    protected function buildFinancialPlansSummary(int $userId): string
    {
        $plans = FinancialPlan::where('user_id', $userId)
            ->active()
            ->with('periods')
            ->get();

        if ($plans->isEmpty()) {
            return '';
        }

        $lines = ["## Financial Plans"];

        foreach ($plans as $plan) {
            $currentYearPeriod = $plan->periods->firstWhere('year', now()->year);

            if ($currentYearPeriod) {
                $lines[] = "- {$plan->name} ({$plan->start_year}-{$plan->end_year}): " .
                    "This year target - Income: {$currentYearPeriod->planned_income}, " .
                    "Expense: {$currentYearPeriod->planned_expense}";
            } else {
                $lines[] = "- {$plan->name} ({$plan->start_year}-{$plan->end_year})";
            }
        }

        return implode("\n", $lines);
    }

    protected function buildIncomeExpenseTrend(int $userId): string
    {
        $months = collect();

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push([
                'label' => $date->format('Y-m'),
                'start' => $date->copy()->startOfMonth()->toDateString(),
                'end' => $date->copy()->endOfMonth()->toDateString(),
            ]);
        }

        $trend = $months->map(function ($month) use ($userId) {
            $data = Transaction::where('user_id', $userId)
                ->whereBetween('transaction_date', [$month['start'], $month['end']])
                ->selectRaw("
                    SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
                ")
                ->first();

            $income = (float) ($data->income ?? 0);
            $expense = (float) ($data->expense ?? 0);

            return "{$month['label']}: +{$income}/-{$expense} (net " . ($income - $expense) . ")";
        });

        if ($trend->filter(fn ($t) => ! str_contains($t, '+0/-0'))->isEmpty()) {
            return '';
        }

        return "## 6-Month Trend\n" . $trend->implode("\n");
    }
}
