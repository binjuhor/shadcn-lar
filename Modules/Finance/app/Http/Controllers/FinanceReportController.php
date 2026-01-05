<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Category;
use Modules\Finance\Models\Currency;
use Modules\Finance\Models\Transaction;
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
            if (!isset($periods[$tx->period])) {
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

            if (!isset($categoryTotals[$catId])) {
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

            if (!isset($categoryTotals[$catId])) {
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

        $passiveCategories = Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })
            ->where('is_passive', true)
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
                'surplus' => 0,
                'passiveCoverage' => 0,
            ];
            $current = $current->addMonth();
        }

        foreach ($transactions as $tx) {
            if (!isset($periods[$tx->period])) {
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
            }
        }

        foreach ($periods as &$p) {
            $p['surplus'] = $p['passiveIncome'] - $p['expense'];
            $p['passiveCoverage'] = $p['expense'] > 0
                ? round(($p['passiveIncome'] / $p['expense']) * 100, 1)
                : 0;
        }

        $monthlyData = array_values($periods);

        $avgPassiveIncome = count($monthlyData) > 0
            ? array_sum(array_column($monthlyData, 'passiveIncome')) / count($monthlyData)
            : 0;
        $avgExpense = count($monthlyData) > 0
            ? array_sum(array_column($monthlyData, 'expense')) / count($monthlyData)
            : 0;
        $avgCoverage = $avgExpense > 0 ? round(($avgPassiveIncome / $avgExpense) * 100, 1) : 0;

        return [
            'monthlyData' => $monthlyData,
            'averages' => [
                'passiveIncome' => round($avgPassiveIncome),
                'expense' => round($avgExpense),
                'coverage' => $avgCoverage,
            ],
            'financialFreedomProgress' => min(100, $avgCoverage),
        ];
    }

    protected function getAccountDistribution(int $userId, string $defaultCode): array
    {
        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->where('exclude_from_total', false)
            ->get();

        $typeLabels = [
            'bank' => 'Bank Accounts',
            'investment' => 'Investments',
            'cash' => 'Cash',
            'credit_card' => 'Credit Cards',
            'loan' => 'Loans',
            'other' => 'Other',
        ];

        $typeColors = [
            'bank' => 'hsl(142, 76%, 36%)',
            'investment' => 'hsl(199, 89%, 48%)',
            'cash' => 'hsl(43, 96%, 56%)',
            'credit_card' => 'hsl(0, 84%, 60%)',
            'loan' => 'hsl(0, 72%, 51%)',
            'other' => 'hsl(220, 9%, 46%)',
        ];

        $liabilityTypes = ['credit_card', 'loan'];

        $distribution = [];

        foreach ($accounts as $account) {
            $type = $account->account_type;
            $balance = $this->convertToDefault(
                (float) $account->current_balance,
                $account->currency_code,
                $defaultCode,
                $account->rate_source
            );

            if (!isset($distribution[$type])) {
                $distribution[$type] = [
                    'type' => $type,
                    'label' => $typeLabels[$type] ?? ucfirst($type),
                    'color' => $typeColors[$type] ?? '#6b7280',
                    'balance' => 0,
                    'count' => 0,
                    'isLiability' => in_array($type, $liabilityTypes),
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
}
