<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Modules\Finance\Models\{FinancialPlan, Transaction};

/**
 * Planned vs actual income and expense per plan year, with actuals converted into the plan currency.
 */
class PlanComparisonService
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function compare(FinancialPlan $plan, int $userId): array
    {
        $comparison = [];

        foreach ($plan->periods as $period) {
            $yearStart = Carbon::create($period->year, 1, 1)->startOfDay();
            $yearEnd = Carbon::create($period->year, 12, 31)->endOfDay();

            $actualIncome = $this->actualTotal($userId, 'income', $yearStart, $yearEnd, $plan->currency_code);
            $actualExpense = $this->actualTotal($userId, 'expense', $yearStart, $yearEnd, $plan->currency_code);

            $comparison[] = [
                'year' => $period->year,
                'planned_income' => $period->planned_income,
                'planned_expense' => $period->planned_expense,
                'actual_income' => $actualIncome['total'],
                'actual_expense' => $actualExpense['total'],
                'income_variance' => $actualIncome['total'] - $period->planned_income,
                'expense_variance' => $actualExpense['total'] - $period->planned_expense,
                'income_variance_percent' => $period->planned_income > 0
                    ? round((($actualIncome['total'] - $period->planned_income) / $period->planned_income) * 100, 1)
                    : 0,
                'expense_variance_percent' => $period->planned_expense > 0
                    ? round((($actualExpense['total'] - $period->planned_expense) / $period->planned_expense) * 100, 1)
                    : 0,
                'unconverted_currencies' => array_values(array_unique([
                    ...$actualIncome['skipped'],
                    ...$actualExpense['skipped'],
                ])),
            ];
        }

        return $comparison;
    }

    /**
     * One year's income or expense in the plan currency. Totals are summed per currency and converted; a currency
     * without a stored rate is left out (and reported in `skipped`) rather than added at face value.
     *
     * @return array{total: float, skipped: list<string>}
     */
    protected function actualTotal(int $userId, string $type, Carbon $from, Carbon $to, string $planCurrency): array
    {
        $totals = Transaction::whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->where('transaction_type', $type)
            ->whereBetween('transaction_date', [$from, $to])
            ->groupBy('currency_code')
            ->selectRaw('currency_code, SUM(amount) as total')
            ->pluck('total', 'currency_code');

        $sum = 0.0;
        $skipped = [];
        foreach ($totals as $currency => $total) {
            $code = (string) ($currency ?: $planCurrency);
            try {
                $sum += $this->exchangeRateService->convert((float) $total, $code, $planCurrency);
            } catch (\RuntimeException) {
                $skipped[] = $code;
            }
        }

        return ['total' => round($sum, 2), 'skipped' => $skipped];
    }
}
