<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Finance\Models\{Currency, Transaction};

class FinancialReportContextBuilder
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function build(int $userId): string
    {
        $report = $this->buildReport($userId);

        if ($report['transaction_count'] === 0) {
            return '';
        }

        return $this->formatReport($report);
    }

    public function buildReport(int $userId, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $currency = $this->getDefaultCurrency();
        $yearStart = $asOf->copy()->startOfYear();
        $yearEnd = $asOf->copy()->endOfYear();

        $transactions = Transaction::query()
            ->without('media')
            ->with(['category:id,name,type', 'account:id,name,user_id,currency_code,rate_source'])
            ->where('user_id', $userId)
            ->whereIn('transaction_type', ['income', 'expense'])
            ->whereNull('transfer_account_id')
            ->orderBy('transaction_date')
            ->get();

        $yearTransactions = $transactions
            ->filter(fn (Transaction $transaction) => $transaction->transaction_date?->betweenIncluded($yearStart, $asOf));

        $monthlyReport = $this->buildMonthlyReport($yearTransactions, $currency);

        return [
            'currency_code' => $currency,
            'as_of' => $asOf->toDateString(),
            'transaction_count' => $transactions->count(),
            'year' => $asOf->year,
            'all_time' => $this->summarizeTransactions($transactions, $currency),
            'year_to_date' => $this->summarizeTransactions($yearTransactions, $currency),
            'monthly' => $monthlyReport,
            'monthly_insights' => $this->buildMonthlyInsights($monthlyReport),
            'top_expense_categories_all_time' => $this->buildCategoryReport($transactions, $currency, 'expense'),
            'top_expense_categories_ytd' => $this->buildCategoryReport($yearTransactions, $currency, 'expense'),
            'top_income_categories_ytd' => $this->buildCategoryReport($yearTransactions, $currency, 'income'),
            'account_activity_ytd' => $this->buildAccountReport($yearTransactions, $currency),
            'year_end_projection' => $this->buildYearEndProjection($yearTransactions, $currency, $asOf, $yearEnd),
            'recent_transactions' => $this->buildRecentTransactions($transactions, $currency),
        ];
    }

    public function buildChartBlocksForQuestion(int $userId, string $question, ?Carbon $asOf = null): array
    {
        if (! preg_match('/\b(chart|graph|visual|report|bar|line|pie|trend|breakdown|biểu đồ|báo cáo)\b/i', $question)) {
            return [];
        }

        $report = $this->buildReport($userId, $asOf);

        if ($report['transaction_count'] === 0) {
            return [];
        }

        $blocks = [];
        $lowerQuestion = mb_strtolower($question);

        if (
            str_contains($lowerQuestion, 'category') ||
            str_contains($lowerQuestion, 'expense') ||
            str_contains($lowerQuestion, 'spending') ||
            str_contains($lowerQuestion, 'danh mục') ||
            str_contains($lowerQuestion, 'chi tiêu')
        ) {
            $blocks[] = $this->makeChartBlock(
                'bar',
                'Top expense categories',
                $report['currency_code'],
                collect($report['top_expense_categories_ytd'])
                    ->take(8)
                    ->map(fn (array $row) => [
                        'label' => $row['category'],
                        'amount' => $row['amount'],
                    ])
                    ->values()
                    ->all(),
                ['amount' => 'Expenses']
            );
        }

        $blocks[] = $this->makeChartBlock(
            'bar',
            "{$report['year']} income vs expenses",
            $report['currency_code'],
            collect($report['monthly'])
                ->map(fn (array $row) => [
                    'label' => $row['month'],
                    'income' => $row['income'],
                    'expense' => $row['expense'],
                    'net' => $row['net'],
                ])
                ->values()
                ->all(),
            ['income' => 'Income', 'expense' => 'Expenses', 'net' => 'Net']
        );

        return array_values(array_filter($blocks, fn (array $block) => ! empty($block['data'])));
    }

    protected function summarizeTransactions(Collection $transactions, string $currency): array
    {
        $income = $this->sumByType($transactions, $currency, 'income');
        $expense = $this->sumByType($transactions, $currency, 'expense');

        return [
            'income' => $this->roundMoney($income),
            'expense' => $this->roundMoney($expense),
            'net' => $this->roundMoney($income - $expense),
            'savings_rate' => $income > 0 ? round((($income - $expense) / $income) * 100, 1) : null,
            'income_transactions' => $transactions->where('transaction_type', 'income')->count(),
            'expense_transactions' => $transactions->where('transaction_type', 'expense')->count(),
        ];
    }

    protected function buildMonthlyReport(Collection $transactions, string $currency): array
    {
        return $transactions
            ->groupBy(fn (Transaction $transaction) => $transaction->transaction_date->format('Y-m'))
            ->map(function (Collection $monthTransactions, string $month) use ($currency) {
                $summary = $this->summarizeTransactions($monthTransactions, $currency);

                return [
                    'month' => $month,
                    ...$summary,
                    'top_expense_categories' => $this->buildCategoryReport($monthTransactions, $currency, 'expense', 5),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    protected function buildMonthlyInsights(array $monthlyReport): array
    {
        $months = collect($monthlyReport);

        if ($months->isEmpty()) {
            return [
                'highest_income_month' => null,
                'highest_expense_month' => null,
                'lowest_net_month' => null,
                'best_net_month' => null,
                'average_monthly_income' => 0,
                'average_monthly_expense' => 0,
                'high_spend_months' => [],
            ];
        }

        $averageExpense = $this->roundMoney($months->avg('expense'));
        $averageIncome = $this->roundMoney($months->avg('income'));
        $highSpendThreshold = $averageExpense * 1.2;

        return [
            'highest_income_month' => $months->sortByDesc('income')->first(),
            'highest_expense_month' => $months->sortByDesc('expense')->first(),
            'lowest_net_month' => $months->sortBy('net')->first(),
            'best_net_month' => $months->sortByDesc('net')->first(),
            'average_monthly_income' => $averageIncome,
            'average_monthly_expense' => $averageExpense,
            'high_spend_months' => $months
                ->filter(fn (array $month) => $month['expense'] > 0 && $month['expense'] >= $highSpendThreshold)
                ->sortByDesc('expense')
                ->values()
                ->all(),
        ];
    }

    protected function buildCategoryReport(Collection $transactions, string $currency, string $type, int $limit = 10): array
    {
        $typedTransactions = $transactions->where('transaction_type', $type);
        $total = $typedTransactions->sum(fn (Transaction $transaction) => $this->amountInDefaultCurrency($transaction, $currency));

        if ($total <= 0) {
            return [];
        }

        return $typedTransactions
            ->groupBy(fn (Transaction $transaction) => $transaction->category?->name ?? 'Uncategorized')
            ->map(function (Collection $categoryTransactions, string $category) use ($currency, $total) {
                $amount = $categoryTransactions->sum(fn (Transaction $transaction) => $this->amountInDefaultCurrency($transaction, $currency));

                return [
                    'category' => $category,
                    'amount' => $this->roundMoney($amount),
                    'percentage' => round(($amount / $total) * 100, 1),
                    'transactions' => $categoryTransactions->count(),
                ];
            })
            ->sortByDesc('amount')
            ->take($limit)
            ->values()
            ->all();
    }

    protected function buildAccountReport(Collection $transactions, string $currency): array
    {
        return $transactions
            ->groupBy(fn (Transaction $transaction) => $transaction->account?->name ?? 'Unknown account')
            ->map(function (Collection $accountTransactions, string $account) use ($currency) {
                $summary = $this->summarizeTransactions($accountTransactions, $currency);

                return [
                    'account' => $account,
                    ...$summary,
                    'transactions' => $accountTransactions->count(),
                ];
            })
            ->sortByDesc(fn (array $row) => abs($row['income']) + abs($row['expense']))
            ->values()
            ->all();
    }

    protected function buildYearEndProjection(Collection $yearTransactions, string $currency, Carbon $asOf, Carbon $yearEnd): array
    {
        $yearStart = $asOf->copy()->startOfYear()->startOfDay();
        $asOfDay = $asOf->copy()->startOfDay();
        $yearEndDay = $yearEnd->copy()->startOfDay();
        $elapsedDays = max(1, (int) $yearStart->diffInDays($asOfDay) + 1);
        $yearDays = max(1, (int) $yearStart->diffInDays($yearEndDay) + 1);
        $remainingDays = max(0, $yearDays - $elapsedDays);
        $summary = $this->summarizeTransactions($yearTransactions, $currency);
        $factor = $yearDays / $elapsedDays;

        return [
            'basis' => "Actual transactions from {$asOf->copy()->startOfYear()->toDateString()} through {$asOf->toDateString()}",
            'elapsed_days' => $elapsedDays,
            'remaining_days' => $remainingDays,
            'projected_income' => $this->roundMoney($summary['income'] * $factor),
            'projected_expense' => $this->roundMoney($summary['expense'] * $factor),
            'projected_net' => $this->roundMoney($summary['net'] * $factor),
            'average_daily_income' => $this->roundMoney($summary['income'] / $elapsedDays),
            'average_daily_expense' => $this->roundMoney($summary['expense'] / $elapsedDays),
        ];
    }

    protected function buildRecentTransactions(Collection $transactions, string $currency): array
    {
        return $transactions
            ->sortByDesc('transaction_date')
            ->take(15)
            ->map(fn (Transaction $transaction) => [
                'date' => $transaction->transaction_date?->toDateString(),
                'type' => $transaction->transaction_type,
                'description' => $transaction->description,
                'category' => $transaction->category?->name ?? 'Uncategorized',
                'account' => $transaction->account?->name,
                'amount' => $this->roundMoney($this->amountInDefaultCurrency($transaction, $currency)),
            ])
            ->values()
            ->all();
    }

    protected function formatReport(array $report): string
    {
        $currency = $report['currency_code'];
        $lines = [
            '## Transaction Reports for Advisor',
            "As of: {$report['as_of']} | Currency: {$currency}",
            "Total transactions available: {$report['transaction_count']}",
            '',
            "### {$report['year']} Year to Date",
            $this->formatSummaryLine($report['year_to_date'], $currency),
            '',
            '### All Time',
            $this->formatSummaryLine($report['all_time'], $currency),
            '',
            "### {$report['year']} Monthly Business Performance",
        ];

        foreach ($report['monthly'] as $month) {
            $lines[] = "- {$month['month']}: ".$this->formatSummaryLine($month, $currency);

            if (! empty($month['top_expense_categories'])) {
                $lines[] = '  Top expenses: '.$this->formatCategoryList($month['top_expense_categories'], $currency);
            }
        }

        $insights = $report['monthly_insights'];
        $lines[] = '';
        $lines[] = '### Monthly Extremes and Overspending Signals';
        $lines[] = 'Average monthly income: '.$insights['average_monthly_income']." {$currency}; average monthly expenses: ".$insights['average_monthly_expense']." {$currency}.";
        $lines[] = 'Highest income month: '.$this->formatMonthlyInsight($insights['highest_income_month'], $currency);
        $lines[] = 'Highest spending month: '.$this->formatMonthlyInsight($insights['highest_expense_month'], $currency);
        $lines[] = 'Lowest net month: '.$this->formatMonthlyInsight($insights['lowest_net_month'], $currency);
        $lines[] = 'Best net month: '.$this->formatMonthlyInsight($insights['best_net_month'], $currency);
        $lines[] = 'High-spend months (>= 120% of average monthly expenses): '.$this->formatMonthlyInsightList($insights['high_spend_months'], $currency);

        $lines[] = '';
        $lines[] = '### Most Expensive Categories';
        $lines[] = 'YTD: '.$this->formatCategoryList($report['top_expense_categories_ytd'], $currency);
        $lines[] = 'All time: '.$this->formatCategoryList($report['top_expense_categories_all_time'], $currency);
        $lines[] = '';
        $lines[] = '### Top Income Categories YTD';
        $lines[] = $this->formatCategoryList($report['top_income_categories_ytd'], $currency);
        $lines[] = '';
        $lines[] = '### Account Activity YTD';

        foreach (array_slice($report['account_activity_ytd'], 0, 8) as $account) {
            $lines[] = "- {$account['account']}: ".$this->formatSummaryLine($account, $currency)." ({$account['transactions']} tx)";
        }

        $projection = $report['year_end_projection'];
        $lines[] = '';
        $lines[] = '### End of Year Estimate';
        $lines[] = "{$projection['basis']}; elapsed days: {$projection['elapsed_days']}; remaining days: {$projection['remaining_days']}.";
        $lines[] = "Projected income: {$projection['projected_income']} {$currency}; projected expenses: {$projection['projected_expense']} {$currency}; projected net: {$projection['projected_net']} {$currency}.";
        $lines[] = "Average per day so far: income {$projection['average_daily_income']} {$currency}; expenses {$projection['average_daily_expense']} {$currency}.";
        $lines[] = '';
        $lines[] = '### Recent Transactions';

        foreach ($report['recent_transactions'] as $transaction) {
            $description = $transaction['description'] ?: 'No description';
            $lines[] = "- {$transaction['date']} {$transaction['type']} {$transaction['amount']} {$currency}: {$description} ({$transaction['category']}, {$transaction['account']})";
        }

        return implode("\n", $lines);
    }

    protected function makeChartBlock(string $type, string $title, string $currency, array $data, array $series): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'currency' => $currency,
            'data' => $data,
            'series' => $series,
        ];
    }

    protected function formatSummaryLine(array $summary, string $currency): string
    {
        $savingsRate = $summary['savings_rate'] === null ? 'n/a' : "{$summary['savings_rate']}%";

        return "income {$summary['income']} {$currency}; expenses {$summary['expense']} {$currency}; net {$summary['net']} {$currency}; savings rate {$savingsRate}";
    }

    protected function formatMonthlyInsight(?array $month, string $currency): string
    {
        if (! $month) {
            return 'No monthly data.';
        }

        return "{$month['month']} (income {$month['income']} {$currency}; expenses {$month['expense']} {$currency}; net {$month['net']} {$currency})";
    }

    protected function formatMonthlyInsightList(array $months, string $currency): string
    {
        if (empty($months)) {
            return 'No month crossed the high-spend threshold.';
        }

        return collect($months)
            ->map(fn (array $month) => $this->formatMonthlyInsight($month, $currency))
            ->implode('; ');
    }

    protected function formatCategoryList(array $categories, string $currency): string
    {
        if (empty($categories)) {
            return 'No categorized transactions.';
        }

        return collect($categories)
            ->map(fn (array $row) => "{$row['category']} {$row['amount']} {$currency} ({$row['percentage']}%, {$row['transactions']} tx)")
            ->implode('; ');
    }

    protected function sumByType(Collection $transactions, string $currency, string $type): float
    {
        return $transactions
            ->where('transaction_type', $type)
            ->sum(fn (Transaction $transaction) => $this->amountInDefaultCurrency($transaction, $currency));
    }

    protected function amountInDefaultCurrency(Transaction $transaction, string $defaultCurrency): float
    {
        $amount = (float) $transaction->amount;

        if ($transaction->currency_code === $defaultCurrency) {
            return $amount;
        }

        try {
            return $this->exchangeRateService->convert(
                $amount,
                $transaction->currency_code,
                $defaultCurrency,
                $transaction->account?->rate_source
            );
        } catch (\Throwable) {
            return $amount;
        }
    }

    protected function getDefaultCurrency(): string
    {
        $user = auth()->user();
        $userSettings = $user?->finance_settings ?? [];

        return $userSettings['default_currency'] ?? Currency::where('is_default', true)->first()?->code ?? 'VND';
    }

    protected function roundMoney(float $amount): float
    {
        return round($amount, 2);
    }
}
