<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Category;
use Modules\Finance\Models\Currency;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\ExchangeRateService;

class ReportApiController extends Controller
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $defaultCode = $this->getDefaultCurrency();

        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $summary = $this->getSummary($userId, $dateFrom, $dateTo, $defaultCode);
        $incomeExpenseTrend = $this->getIncomeExpenseTrend($userId, $dateFrom, $dateTo, $defaultCode);

        return response()->json([
            'data' => [
                'summary' => $summary,
                'trend' => $incomeExpenseTrend,
                'currency_code' => $defaultCode,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
        ]);
    }

    public function incomeExpenseTrend(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $defaultCode = $this->getDefaultCurrency();

        [$dateFrom, $dateTo, $groupBy] = $this->parseDateRange($request, true);

        $trend = $this->getIncomeExpenseTrend($userId, $dateFrom, $dateTo, $defaultCode, $groupBy);

        return response()->json([
            'data' => $trend,
            'currency_code' => $defaultCode,
        ]);
    }

    public function categoryBreakdown(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $defaultCode = $this->getDefaultCurrency();
        $type = $request->get('type', 'expense');

        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $breakdown = $this->getCategoryBreakdown($userId, $dateFrom, $dateTo, $defaultCode, $type);

        return response()->json([
            'data' => $breakdown,
            'type' => $type,
            'currency_code' => $defaultCode,
        ]);
    }

    public function accountDistribution(): JsonResponse
    {
        $userId = auth()->id();
        $defaultCode = $this->getDefaultCurrency();

        $distribution = $this->getAccountDistribution($userId, $defaultCode);

        return response()->json([
            'data' => $distribution,
            'currency_code' => $defaultCode,
        ]);
    }

    public function cashflowAnalysis(): JsonResponse
    {
        $userId = auth()->id();
        $defaultCode = $this->getDefaultCurrency();

        $analysis = $this->getCashflowAnalysis($userId, $defaultCode);

        return response()->json([
            'data' => $analysis,
            'currency_code' => $defaultCode,
        ]);
    }

    public function netWorth(): JsonResponse
    {
        $userId = auth()->id();
        $defaultCode = $this->getDefaultCurrency();

        $allAccounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $includedAccounts = $allAccounts->where('exclude_from_total', false);

        // Assets: only from accounts where exclude_from_total = false
        $totalAssets = $includedAccounts
            ->whereIn('account_type', ['bank', 'investment', 'cash', 'e_wallet'])
            ->where('current_balance', '>', 0)
            ->sum(fn ($account) => $this->convertToDefault(
                (float) $account->current_balance,
                $account->currency_code,
                $defaultCode,
                $account->rate_source
            ));

        // Liabilities: from ALL credit cards/loans (debt is always tracked)
        $totalLiabilities = $allAccounts
            ->whereIn('account_type', ['credit_card', 'loan'])
            ->sum(function ($account) use ($defaultCode) {
                $amountOwed = $account->initial_balance - $account->current_balance;
                if ($amountOwed <= 0) {
                    return 0;
                }

                return $this->convertToDefault(
                    $amountOwed,
                    $account->currency_code,
                    $defaultCode,
                    $account->rate_source
                );
            });

        return response()->json([
            'data' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'net_worth' => $totalAssets - $totalLiabilities,
                'accounts_count' => $includedAccounts->count(),
            ],
            'currency_code' => $defaultCode,
        ]);
    }

    protected function getDefaultCurrency(): string
    {
        $user = auth()->user();
        $userSettings = $user->finance_settings ?? [];

        return $userSettings['default_currency'] ?? Currency::where('is_default', true)->first()?->code ?? 'VND';
    }

    protected function parseDateRange(Request $request, bool $includeGroupBy = false): array
    {
        $range = $request->get('range', '6m');
        $startDate = $request->get('start');
        $endDate = $request->get('end');
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
                $dateFrom = $startDate ? Carbon::parse($startDate) : $now->copy()->subMonths(6);
                $dateTo = $endDate ? Carbon::parse($endDate) : $now->copy();
                $daysDiff = $dateFrom->diffInDays($dateTo);
                $groupBy = $daysDiff <= 60 ? 'day' : 'month';
                break;
            default:
                $dateFrom = $now->copy()->subMonths(6)->startOfMonth();
                $dateTo = $now->copy()->endOfMonth();
                $groupBy = 'month';
        }

        if ($includeGroupBy) {
            return [$dateFrom, $dateTo, $groupBy];
        }

        return [$dateFrom, $dateTo];
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

        $transactionCount = Transaction::whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('transaction_date', [$dateFrom->startOfDay(), $dateTo->endOfDay()])
            ->count();

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_change' => $totalIncome - $totalExpense,
            'savings_rate' => $totalIncome > 0 ? round((($totalIncome - $totalExpense) / $totalIncome) * 100, 1) : 0,
            'transaction_count' => $transactionCount,
        ];
    }

    protected function getIncomeExpenseTrend(int $userId, Carbon $dateFrom, Carbon $dateTo, string $defaultCode, string $groupBy = 'month'): array
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
                'net' => 0,
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

        foreach ($periods as &$p) {
            $p['net'] = $p['income'] - $p['expense'];
        }

        return array_values($periods);
    }

    protected function getCategoryBreakdown(int $userId, Carbon $dateFrom, Carbon $dateTo, string $defaultCode, string $type = 'expense'): array
    {
        $transactions = Transaction::select(
            'category_id',
            DB::raw('SUM(amount) as total'),
            'currency_code'
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->where('transaction_type', $type)
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
                    'icon' => $category?->icon,
                    'amount' => 0,
                ];
            }

            $categoryTotals[$catId]['amount'] += $amount;
        }

        usort($categoryTotals, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $grandTotal = array_sum(array_column($categoryTotals, 'amount'));

        return array_map(function ($cat) use ($grandTotal) {
            return [
                ...$cat,
                'percentage' => $grandTotal > 0 ? round(($cat['amount'] / $grandTotal) * 100, 1) : 0,
            ];
        }, $categoryTotals);
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
            'e_wallet' => 'E-Wallets',
            'credit_card' => 'Credit Cards',
            'loan' => 'Loans',
            'other' => 'Other',
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

            if (! isset($distribution[$type])) {
                $distribution[$type] = [
                    'type' => $type,
                    'label' => $typeLabels[$type] ?? ucfirst($type),
                    'balance' => 0,
                    'count' => 0,
                    'is_liability' => in_array($type, $liabilityTypes),
                ];
            }

            $distribution[$type]['balance'] += $balance;
            $distribution[$type]['count']++;
        }

        usort($distribution, fn ($a, $b) => abs($b['balance']) <=> abs($a['balance']));

        return array_values($distribution);
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
                'passive_income' => 0,
                'active_income' => 0,
                'total_income' => 0,
                'expense' => 0,
                'essential_expense' => 0,
                'surplus' => 0,
                'passive_coverage' => 0,
            ];
            $current = $current->addMonth();
        }

        foreach ($transactions as $tx) {
            if (! isset($periods[$tx->period])) {
                continue;
            }

            $amount = $this->convertToDefault((float) $tx->total, $tx->currency_code, $defaultCode);

            if ($tx->transaction_type === 'income') {
                $periods[$tx->period]['total_income'] += $amount;
                if (in_array($tx->category_id, $passiveCategories)) {
                    $periods[$tx->period]['passive_income'] += $amount;
                } else {
                    $periods[$tx->period]['active_income'] += $amount;
                }
            } else {
                $periods[$tx->period]['expense'] += $amount;
                // Track essential expenses separately for financial freedom calculation
                if (in_array($tx->category_id, $essentialCategories)) {
                    $periods[$tx->period]['essential_expense'] += $amount;
                }
            }
        }

        foreach ($periods as &$p) {
            $p['surplus'] = $p['passive_income'] - $p['expense'];
            // Coverage based on essential expenses (financial freedom = covering needs)
            $p['passive_coverage'] = $p['essential_expense'] > 0
                ? round(($p['passive_income'] / $p['essential_expense']) * 100, 1)
                : ($p['expense'] > 0 ? round(($p['passive_income'] / $p['expense']) * 100, 1) : 0);
        }

        $monthlyData = array_values($periods);

        $avgPassiveIncome = count($monthlyData) > 0
            ? array_sum(array_column($monthlyData, 'passive_income')) / count($monthlyData)
            : 0;
        $avgExpense = count($monthlyData) > 0
            ? array_sum(array_column($monthlyData, 'expense')) / count($monthlyData)
            : 0;
        $avgEssentialExpense = count($monthlyData) > 0
            ? array_sum(array_column($monthlyData, 'essential_expense')) / count($monthlyData)
            : 0;

        // Financial freedom = passive income covering essential expenses
        $avgCoverage = $avgEssentialExpense > 0
            ? round(($avgPassiveIncome / $avgEssentialExpense) * 100, 1)
            : ($avgExpense > 0 ? round(($avgPassiveIncome / $avgExpense) * 100, 1) : 0);

        return [
            'monthly_data' => $monthlyData,
            'averages' => [
                'passive_income' => round($avgPassiveIncome),
                'expense' => round($avgExpense),
                'essential_expense' => round($avgEssentialExpense),
                'coverage' => $avgCoverage,
            ],
            'financial_freedom_progress' => min(100, $avgCoverage),
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
