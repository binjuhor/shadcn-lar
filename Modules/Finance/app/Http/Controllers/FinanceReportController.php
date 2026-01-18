<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Inertia\{Inertia, Response};
use Modules\Finance\Models\{Account, Category, Currency, Transaction};
use Modules\Finance\Services\ExchangeRateService;

class FinanceReportController extends Controller
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function index(Request $request): Response
    {
        $userId = auth()->id();
        $defaultCurrency = Currency::where('is_default', true)->first();
        $defaultCode = $defaultCurrency?->code ?? 'VND';

        $range = $request->get('range', '6m');
        $startDate = $request->get('start');
        $endDate = $request->get('end');

        [$dateFrom, $dateTo, $groupBy] = $this->parseDateRange($range, $startDate, $endDate);

        $incomeExpenseTrend = $this->getIncomeExpenseTrend($userId, $dateFrom, $dateTo, $groupBy, $defaultCode);
        $categoryBreakdown = $this->getCategoryBreakdown($userId, $dateFrom, $dateTo, $defaultCode);
        $incomeCategoryBreakdown = $this->getIncomeCategoryBreakdown($userId, $dateFrom, $dateTo, $defaultCode);
        $accountDistribution = $this->getAccountDistribution($userId, $defaultCode);
        $cashflowAnalysis = $this->getCashflowAnalysis($userId, $defaultCode);
        $summary = $this->getSummary($userId, $dateFrom, $dateTo, $defaultCode);

        // Get categories for the category trend selector
        $categories = Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Finance::reports/index', [
            'filters' => [
                'range' => $range,
                'startDate' => $dateFrom->format('Y-m-d'),
                'endDate' => $dateTo->format('Y-m-d'),
            ],
            'incomeExpenseTrend' => $incomeExpenseTrend,
            'categoryBreakdown' => $categoryBreakdown,
            'incomeCategoryBreakdown' => $incomeCategoryBreakdown,
            'accountDistribution' => $accountDistribution,
            'cashflowAnalysis' => $cashflowAnalysis,
            'summary' => $summary,
            'categories' => $categories,
            'currencyCode' => $defaultCode,
        ]);
    }

    protected function parseDateRange(string $range, ?string $start, ?string $end): array
    {
        $now = Carbon::now();

        switch ($range) {
            case '30d':
                $dateFrom = $now->copy()->subDays(30);
                $dateTo = $now->copy();
                $groupBy = 'day';
                break;
            case '6m':
                $dateFrom = $now->copy()->subMonths(6)->startOfMonth();
                $dateTo = $now->copy()->endOfMonth();
                $groupBy = 'month';
                break;
            case '12m':
                $dateFrom = $now->copy()->subMonths(12)->startOfMonth();
                $dateTo = $now->copy()->endOfMonth();
                $groupBy = 'month';
                break;
            case 'ytd':
                $dateFrom = $now->copy()->startOfYear();
                $dateTo = $now->copy();
                $groupBy = 'month';
                break;
            case 'custom':
                $dateFrom = $start ? Carbon::parse($start) : $now->copy()->subMonths(6);
                $dateTo = $end ? Carbon::parse($end) : $now->copy();
                $daysDiff = $dateFrom->diffInDays($dateTo);
                $groupBy = $daysDiff <= 60 ? 'day' : 'month';
                break;
            default:
                $dateFrom = $now->copy()->subMonths(6)->startOfMonth();
                $dateTo = $now->copy()->endOfMonth();
                $groupBy = 'month';
        }

        return [$dateFrom, $dateTo, $groupBy];
    }

    protected function getIncomeExpenseTrend(int $userId, Carbon $dateFrom, Carbon $dateTo, string $groupBy, string $defaultCode): array
    {
        $dateFormat = $groupBy === 'day' ? '%Y-%m-%d' : '%Y-%m';
        $phpFormat = $groupBy === 'day' ? 'Y-m-d' : 'Y-m';

        $transactions = Transaction::select(
            DB::raw("DATE_FORMAT(transaction_date, '{$dateFormat}') as period"),
            'transaction_type',
            DB::raw('SUM(amount) as total'),
            'currency_code'
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('transaction_type', ['income', 'expense'])
            ->whereBetween('transaction_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->groupBy('period', 'transaction_type', 'currency_code')
            ->get();

        $periods = [];
        $current = $dateFrom->copy();

        while ($current <= $dateTo) {
            $periodKey = $current->format($phpFormat);
            $periods[$periodKey] = [
                'period' => $periodKey,
                'income' => 0,
                'expense' => 0,
            ];
            $current = $groupBy === 'day' ? $current->addDay() : $current->addMonth();
        }

        foreach ($transactions as $tx) {
            if (! isset($periods[$tx->period])) {
                continue;
            }

            $amount = $this->convertToDefault((float) $tx->total, $tx->currency_code, $defaultCode);

            if ($tx->transaction_type === 'income') {
                $periods[$tx->period]['income'] += $amount;
            } else {
                $periods[$tx->period]['expense'] += $amount;
            }
        }

        return array_values($periods);
    }

    protected function getCategoryBreakdown(int $userId, Carbon $dateFrom, Carbon $dateTo, string $defaultCode): array
    {
        $transactions = Transaction::select(
            'category_id',
            DB::raw('SUM(amount) as total'),
            'currency_code'
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->whereNotNull('category_id')
            ->groupBy('category_id', 'currency_code')
            ->get();

        $categories = Category::whereIn('id', $transactions->pluck('category_id'))
            ->get()
            ->keyBy('id');

        $categoryTotals = [];

        foreach ($transactions as $tx) {
            $catId = $tx->category_id;
            $amount = $this->convertToDefault((float) $tx->total, $tx->currency_code, $defaultCode);

            if (! isset($categoryTotals[$catId])) {
                $category = $categories->get($catId);
                $categoryTotals[$catId] = [
                    'id' => $catId,
                    'name' => $category?->name ?? 'Unknown',
                    'color' => $category?->color ?? '#6b7280',
                    'amount' => 0,
                ];
            }

            $categoryTotals[$catId]['amount'] += $amount;
        }

        usort($categoryTotals, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $top = array_slice($categoryTotals, 0, 7);
        $others = array_slice($categoryTotals, 7);

        if (count($others) > 0) {
            $othersTotal = array_sum(array_column($others, 'amount'));
            $top[] = [
                'id' => 0,
                'name' => 'Others',
                'color' => '#9ca3af',
                'amount' => $othersTotal,
            ];
        }

        $grandTotal = array_sum(array_column($top, 'amount'));

        return array_map(function ($cat) use ($grandTotal) {
            return [
                ...$cat,
                'percentage' => $grandTotal > 0 ? round(($cat['amount'] / $grandTotal) * 100, 1) : 0,
            ];
        }, $top);
    }

    protected function getIncomeCategoryBreakdown(int $userId, Carbon $dateFrom, Carbon $dateTo, string $defaultCode): array
    {
        $transactions = Transaction::select(
            'category_id',
            DB::raw('SUM(amount) as total'),
            'currency_code'
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->where('transaction_type', 'income')
            ->whereBetween('transaction_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->whereNotNull('category_id')
            ->groupBy('category_id', 'currency_code')
            ->get();

        $categories = Category::whereIn('id', $transactions->pluck('category_id'))
            ->get()
            ->keyBy('id');

        $categoryTotals = [];

        foreach ($transactions as $tx) {
            $catId = $tx->category_id;
            $amount = $this->convertToDefault((float) $tx->total, $tx->currency_code, $defaultCode);

            if (! isset($categoryTotals[$catId])) {
                $category = $categories->get($catId);
                $categoryTotals[$catId] = [
                    'id' => $catId,
                    'name' => $category?->name ?? 'Unknown',
                    'color' => $category?->color ?? '#10b981',
                    'amount' => 0,
                ];
            }

            $categoryTotals[$catId]['amount'] += $amount;
        }

        usort($categoryTotals, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $top = array_slice($categoryTotals, 0, 7);
        $others = array_slice($categoryTotals, 7);

        if (count($others) > 0) {
            $othersTotal = array_sum(array_column($others, 'amount'));
            $top[] = [
                'id' => 0,
                'name' => 'Others',
                'color' => '#9ca3af',
                'amount' => $othersTotal,
            ];
        }

        $grandTotal = array_sum(array_column($top, 'amount'));

        return array_map(function ($cat) use ($grandTotal) {
            return [
                ...$cat,
                'percentage' => $grandTotal > 0 ? round(($cat['amount'] / $grandTotal) * 100, 1) : 0,
            ];
        }, $top);
    }

    protected function getCashflowAnalysis(int $userId, string $defaultCode): array
    {
        $now = Carbon::now();
        $startDate = $now->copy()->subMonths(11)->startOfMonth();
        $endDate = $now->copy()->endOfMonth();

        $transactions = Transaction::select(
            DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as period"),
            'transaction_type',
            'category_id',
            DB::raw('SUM(amount) as total'),
            'currency_code'
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('transaction_type', ['income', 'expense'])
            ->whereBetween('transaction_date', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('period', 'transaction_type', 'category_id', 'currency_code')
            ->get();

        // Get passive income categories
        $passiveCategories = Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })
            ->where('is_passive', true)
            ->pluck('id')
            ->toArray();

        // Get essential expense categories (needs for survival)
        $essentialCategories = Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })
            ->where('expense_type', 'essential')
            ->pluck('id')
            ->toArray();

        $periods = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periodKey = $current->format('Y-m');
            $periods[$periodKey] = [
                'period' => $periodKey,
                'label' => $current->format('M Y'),
                'passiveIncome' => 0,
                'activeIncome' => 0,
                'totalIncome' => 0,
                'expense' => 0,
                'essentialExpense' => 0,
                'surplus' => 0,
                'passiveCoverage' => 0,
            ];
            $current = $current->addMonth();
        }

        foreach ($transactions as $tx) {
            if (! isset($periods[$tx->period])) {
                continue;
            }

            $amount = $this->convertToDefault((float) $tx->total, $tx->currency_code, $defaultCode);

            if ($tx->transaction_type === 'income') {
                $periods[$tx->period]['totalIncome'] += $amount;
                if (in_array($tx->category_id, $passiveCategories)) {
                    $periods[$tx->period]['passiveIncome'] += $amount;
                } else {
                    $periods[$tx->period]['activeIncome'] += $amount;
                }
            } else {
                $periods[$tx->period]['expense'] += $amount;
                // Track essential expenses separately for financial freedom calculation
                if (in_array($tx->category_id, $essentialCategories)) {
                    $periods[$tx->period]['essentialExpense'] += $amount;
                }
            }
        }

        foreach ($periods as &$p) {
            $p['surplus'] = $p['passiveIncome'] - $p['expense'];
            // Coverage based on essential expenses (financial freedom = covering needs)
            $p['passiveCoverage'] = $p['essentialExpense'] > 0
                ? round(($p['passiveIncome'] / $p['essentialExpense']) * 100, 1)
                : ($p['expense'] > 0 ? round(($p['passiveIncome'] / $p['expense']) * 100, 1) : 0);
        }

        $monthlyData = array_values($periods);

        $avgPassiveIncome = count($monthlyData) > 0
            ? array_sum(array_column($monthlyData, 'passiveIncome')) / count($monthlyData)
            : 0;
        $avgExpense = count($monthlyData) > 0
            ? array_sum(array_column($monthlyData, 'expense')) / count($monthlyData)
            : 0;
        $avgEssentialExpense = count($monthlyData) > 0
            ? array_sum(array_column($monthlyData, 'essentialExpense')) / count($monthlyData)
            : 0;

        // Financial freedom = passive income covering essential expenses
        $avgCoverage = $avgEssentialExpense > 0
            ? round(($avgPassiveIncome / $avgEssentialExpense) * 100, 1)
            : ($avgExpense > 0 ? round(($avgPassiveIncome / $avgExpense) * 100, 1) : 0);

        return [
            'monthlyData' => $monthlyData,
            'averages' => [
                'passiveIncome' => round($avgPassiveIncome),
                'expense' => round($avgExpense),
                'essentialExpense' => round($avgEssentialExpense),
                'coverage' => $avgCoverage,
            ],
            'financialFreedomProgress' => min(100, $avgCoverage),
        ];
    }

    protected function getAccountDistribution(int $userId, string $defaultCode): array
    {
        // Get all active accounts - liabilities always shown, assets respect exclude_from_total
        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('exclude_from_total', false)
                    ->orWhereIn('account_type', ['credit_card', 'loan']);
            })
            ->get();

        $typeLabels = [
            'bank' => 'Bank Accounts',
            'investment' => 'Investments',
            'cash' => 'Cash',
            'e_wallet' => 'E-Wallets',
            'credit_card' => 'Credit Cards',
            'loan' => 'Loans',
            'other' => 'Other',
        ];

        $typeColors = [
            'bank' => 'hsl(142, 76%, 36%)',
            'investment' => 'hsl(199, 89%, 48%)',
            'cash' => 'hsl(43, 96%, 56%)',
            'e_wallet' => 'hsl(271, 91%, 65%)',
            'credit_card' => 'hsl(0, 84%, 60%)',
            'loan' => 'hsl(0, 72%, 51%)',
            'other' => 'hsl(220, 9%, 46%)',
        ];

        $liabilityTypes = ['credit_card', 'loan'];

        $distribution = [];

        foreach ($accounts as $account) {
            $type = $account->account_type;
            $isLiability = in_array($type, $liabilityTypes);

            // For credit accounts: show amount owed (initial_balance - current_balance)
            // For regular accounts: show current_balance
            $rawBalance = $isLiability
                ? ($account->initial_balance - $account->current_balance)
                : $account->current_balance;

            // Skip if no balance to show
            if ($rawBalance <= 0) {
                continue;
            }

            $balance = $this->convertToDefault(
                (float) $rawBalance,
                $account->currency_code,
                $defaultCode,
                $account->rate_source
            );

            if (! isset($distribution[$type])) {
                $distribution[$type] = [
                    'type' => $type,
                    'label' => $typeLabels[$type] ?? ucfirst($type),
                    'color' => $typeColors[$type] ?? '#6b7280',
                    'balance' => 0,
                    'count' => 0,
                    'isLiability' => $isLiability,
                ];
            }

            $distribution[$type]['balance'] += $balance;
            $distribution[$type]['count']++;
        }

        usort($distribution, fn ($a, $b) => abs($b['balance']) <=> abs($a['balance']));

        return array_values($distribution);
    }

    protected function getSummary(int $userId, Carbon $dateFrom, Carbon $dateTo, string $defaultCode): array
    {
        $transactions = Transaction::select(
            'transaction_type',
            DB::raw('SUM(amount) as total'),
            'currency_code'
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('transaction_type', ['income', 'expense'])
            ->whereBetween('transaction_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->groupBy('transaction_type', 'currency_code')
            ->get();

        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($transactions as $tx) {
            $amount = $this->convertToDefault((float) $tx->total, $tx->currency_code, $defaultCode);

            if ($tx->transaction_type === 'income') {
                $totalIncome += $amount;
            } else {
                $totalExpense += $amount;
            }
        }

        $previousFrom = $dateFrom->copy()->subDays($dateFrom->diffInDays($dateTo));
        $previousTo = $dateFrom->copy()->subDay();

        $previousTransactions = Transaction::select(
            'transaction_type',
            DB::raw('SUM(amount) as total'),
            'currency_code'
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('transaction_type', ['income', 'expense'])
            ->whereBetween('transaction_date', [$previousFrom->startOfDay(), $previousTo->endOfDay()])
            ->groupBy('transaction_type', 'currency_code')
            ->get();

        $prevIncome = 0;
        $prevExpense = 0;

        foreach ($previousTransactions as $tx) {
            $amount = $this->convertToDefault((float) $tx->total, $tx->currency_code, $defaultCode);

            if ($tx->transaction_type === 'income') {
                $prevIncome += $amount;
            } else {
                $prevExpense += $amount;
            }
        }

        $prevNet = $prevIncome - $prevExpense;
        $currentNet = $totalIncome - $totalExpense;
        $netChange = $prevNet != 0 ? (($currentNet - $prevNet) / abs($prevNet)) * 100 : 0;

        return [
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netChange' => $currentNet,
            'previousPeriodChange' => round($netChange, 1),
        ];
    }

    protected function convertToDefault(float $amount, string $fromCurrency, string $defaultCurrency, ?string $source = null): float
    {
        if ($fromCurrency === $defaultCurrency) {
            return $amount;
        }

        try {
            return $this->exchangeRateService->convert($amount, $fromCurrency, $defaultCurrency, $source);
        } catch (\Exception) {
            return $amount;
        }
    }

    public function categoryTrend(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $categoryId = $request->get('category_id');

        if (! $categoryId) {
            return response()->json(['data' => null]);
        }

        $defaultCurrency = Currency::where('is_default', true)->first();
        $defaultCode = $defaultCurrency?->code ?? 'VND';

        $category = Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })
            ->where('id', $categoryId)
            ->first();

        if (! $category) {
            return response()->json(['data' => null]);
        }

        // Use the same date parsing as other report methods
        $range = $request->get('range', '6m');
        $start = $request->get('start');
        $end = $request->get('end');

        [$startDate, $endDate, $groupBy] = $this->parseDateRange($range, $start, $end);

        $transactions = Transaction::select(
            DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as period"),
            DB::raw('SUM(amount) as total'),
            DB::raw('COUNT(*) as count'),
            'currency_code'
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->where('category_id', $categoryId)
            ->whereBetween('transaction_date', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('period', 'currency_code')
            ->get();

        $periods = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periodKey = $current->format('Y-m');
            $periods[$periodKey] = [
                'period' => $periodKey,
                'label' => $current->format('M Y'),
                'amount' => 0,
                'transactionCount' => 0,
            ];
            $current = $current->addMonth();
        }

        foreach ($transactions as $tx) {
            if (! isset($periods[$tx->period])) {
                continue;
            }

            $amount = $this->convertToDefault((float) $tx->total, $tx->currency_code, $defaultCode);
            $periods[$tx->period]['amount'] += $amount;
            $periods[$tx->period]['transactionCount'] += $tx->count;
        }

        $monthlyData = array_values($periods);
        $amounts = array_column($monthlyData, 'amount');
        $nonZeroAmounts = array_filter($amounts, fn ($a) => $a > 0);

        $totalAmount = array_sum($amounts);
        $transactionCount = array_sum(array_column($monthlyData, 'transactionCount'));
        $averageAmount = count($nonZeroAmounts) > 0 ? $totalAmount / count($nonZeroAmounts) : 0;

        // Calculate trend (first 3 months avg vs last 3 months avg)
        $firstThree = array_slice($amounts, 0, 3);
        $lastThree = array_slice($amounts, -3, 3);
        $firstAvg = count(array_filter($firstThree)) > 0 ? array_sum($firstThree) / count(array_filter($firstThree)) : 0;
        $lastAvg = count(array_filter($lastThree)) > 0 ? array_sum($lastThree) / count(array_filter($lastThree)) : 0;
        $trend = $firstAvg > 0 ? (($lastAvg - $firstAvg) / $firstAvg) * 100 : 0;

        // Find best and worst months
        $bestMonth = null;
        $worstMonth = null;
        $maxAmount = 0;
        $minAmount = PHP_FLOAT_MAX;

        foreach ($monthlyData as $point) {
            if ($point['amount'] > $maxAmount) {
                $maxAmount = $point['amount'];
                $bestMonth = ['period' => $point['label'], 'amount' => $point['amount']];
            }
            if ($point['amount'] > 0 && $point['amount'] < $minAmount) {
                $minAmount = $point['amount'];
                $worstMonth = ['period' => $point['label'], 'amount' => $point['amount']];
            }
        }

        return response()->json([
            'data' => [
                'category' => $category,
                'monthlyData' => $monthlyData,
                'totalAmount' => round($totalAmount, 2),
                'averageAmount' => round($averageAmount, 2),
                'transactionCount' => $transactionCount,
                'trend' => round($trend, 1),
                'bestMonth' => $bestMonth,
                'worstMonth' => $worstMonth,
            ],
        ]);
    }
}
