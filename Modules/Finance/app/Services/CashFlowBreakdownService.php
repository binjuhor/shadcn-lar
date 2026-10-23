<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\{Category, Transaction};

/**
 * Monthly cash flow split by income source (passive vs active, via
 * `Category.is_passive`) and expense need (essential vs other, via
 * `Category.expense_type`), converted to one currency. Shared by the
 * report pages and the AI assistant so both show the same numbers.
 */
class CashFlowBreakdownService
{
    public function __construct(protected ExchangeRateService $exchangeRateService) {}

    /** @return array<string, mixed> */
    public function breakdown(int $userId, Carbon $start, Carbon $end, string $currency): array
    {
        $start = $start->copy()->startOfMonth()->startOfDay();
        $end = $end->copy()->endOfDay();

        $passiveIds = $this->categoryIds($userId, fn ($query) => $query->where('is_passive', true));
        $essentialIds = $this->categoryIds($userId, fn ($query) => $query->where('expense_type', 'essential'));

        $months = [];
        for ($cursor = $start->copy(); $cursor <= $end; $cursor->addMonth()) {
            $months[$cursor->format('Y-m')] = [
                'period' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'passive_income' => 0.0,
                'active_income' => 0.0,
                'total_income' => 0.0,
                'expense' => 0.0,
                'essential_expense' => 0.0,
                'other_expense' => 0.0,
            ];
        }

        $passiveByCategory = [];

        foreach ($this->rows($userId, $start, $end) as $row) {
            if (! isset($months[$row->period])) {
                continue;
            }

            $amount = $this->convert((float) $row->total, $row->currency_code, $currency);
            $month = &$months[$row->period];

            if ($row->transaction_type === 'income') {
                $month['total_income'] += $amount;
                if (in_array($row->category_id, $passiveIds, true)) {
                    $month['passive_income'] += $amount;
                    $passiveByCategory[$row->category_id] = ($passiveByCategory[$row->category_id] ?? 0) + $amount;
                } else {
                    $month['active_income'] += $amount;
                }
            } else {
                $month['expense'] += $amount;
                $key = in_array($row->category_id, $essentialIds, true) ? 'essential_expense' : 'other_expense';
                $month[$key] += $amount;
            }
            unset($month);
        }

        $monthlyData = array_values(array_map(fn (array $month) => $month + [
            'surplus' => $month['passive_income'] - $month['expense'],
            'net' => $month['total_income'] - $month['expense'],
            'passive_coverage' => $this->coverage($month['passive_income'], $month['essential_expense'], $month['expense']),
        ], $months));

        $count = max(count($monthlyData), 1);
        $sum = fn (string $key) => array_sum(array_column($monthlyData, $key));
        $avgPassive = $sum('passive_income') / $count;
        $avgExpense = $sum('expense') / $count;
        $avgEssential = $sum('essential_expense') / $count;
        $avgCoverage = $this->coverage($avgPassive, $avgEssential, $avgExpense);
        $totalIncome = $sum('total_income');

        return [
            'currency' => $currency,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'monthly_data' => $monthlyData,
            'totals' => [
                'passive_income' => $sum('passive_income'),
                'active_income' => $sum('active_income'),
                'total_income' => $totalIncome,
                'expense' => $sum('expense'),
                'essential_expense' => $sum('essential_expense'),
                'other_expense' => $sum('other_expense'),
                'net' => $totalIncome - $sum('expense'),
                'savings_rate' => $totalIncome > 0 ? round(($totalIncome - $sum('expense')) / $totalIncome * 100, 1) : 0,
            ],
            'averages' => [
                'passive_income' => round($avgPassive),
                'expense' => round($avgExpense),
                'essential_expense' => round($avgEssential),
                'coverage' => $avgCoverage,
            ],
            'financial_freedom_progress' => min(100, $avgCoverage),
            'passive_income_categories' => $this->namedCategories($passiveByCategory),
        ];
    }

    private function rows(int $userId, Carbon $start, Carbon $end)
    {
        return Transaction::query()
            ->select([
                DB::raw($this->monthExpression().' as period'),
                'transaction_type',
                'category_id',
                DB::raw('SUM(amount) as total'),
                'currency_code',
            ])
            ->whereHas('account', fn ($query) => $query->where('user_id', $userId))
            ->whereIn('transaction_type', ['income', 'expense'])
            ->whereNull('transfer_account_id')
            ->whereBetween('transaction_date', [$start, $end])
            ->groupBy('period', 'transaction_type', 'category_id', 'currency_code')
            ->get();
    }

    private function monthExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', transaction_date)"
            : "DATE_FORMAT(transaction_date, '%Y-%m')";
    }

    /** @return array<int, int> */
    private function categoryIds(int $userId, callable $filter): array
    {
        return $filter(Category::userCategories($userId))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @param array<int, float> $totals */
    private function namedCategories(array $totals): array
    {
        arsort($totals);
        $names = Category::whereIn('id', array_keys($totals))->pluck('name', 'id');

        return collect($totals)->map(fn (float $amount, int $id) => [
            'category_id' => $id,
            'category' => $names[$id] ?? null,
            'amount' => $amount,
        ])->values()->all();
    }

    /** Financial freedom = passive income covering essential expenses (all expenses when none flagged). */
    private function coverage(float $passive, float $essential, float $expense): float
    {
        $base = $essential > 0 ? $essential : $expense;

        return $base > 0 ? round($passive / $base * 100, 1) : 0;
    }

    private function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        try {
            return $this->exchangeRateService->convert($amount, $from, $to);
        } catch (\Exception) {
            return $amount;
        }
    }
}
