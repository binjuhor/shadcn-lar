<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Budget;
use Modules\Finance\Models\Currency;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Services\ExchangeRateService;

class FinanceDashboardController extends Controller
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function index(): Response
    {
        $userId = auth()->id();
        $defaultCurrency = Currency::where('is_default', true)->first();
        $defaultCode = $defaultCurrency?->code ?? 'VND';

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->where('exclude_from_total', false)
            ->get();

        $totalAssets = $accounts
            ->whereIn('account_type', ['bank', 'investment', 'cash'])
            ->where('current_balance', '>', 0)
            ->sum(fn ($account) => $this->convertToDefault($account->current_balance, $account->currency_code, $defaultCode, $account->rate_source));

        $totalLiabilities = abs($accounts
            ->whereIn('account_type', ['credit_card', 'loan'])
            ->sum(fn ($account) => $this->convertToDefault($account->current_balance, $account->currency_code, $defaultCode, $account->rate_source)));

        $netWorth = $totalAssets - $totalLiabilities;
        $totalBalance = $accounts->sum(fn ($account) => $this->convertToDefault($account->current_balance, $account->currency_code, $defaultCode, $account->rate_source));

        $recentTransactions = Transaction::with(['account', 'category'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $currentDate = now();
        $budgets = Budget::with('category')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate)
            ->orderBy('allocated_amount', 'desc')
            ->get();

        $spendingTrend = Transaction::select(
            DB::raw('DATE(transaction_date) as date'),
            DB::raw('SUM(amount) as amount')
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->where('transaction_type', 'expense')
            ->where('transaction_date', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'amount' => $item->amount,
            ]);

        return Inertia::render('Finance::index', [
            'summary' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'net_worth' => $netWorth,
                'total_balance' => $totalBalance,
                'currency_code' => $defaultCurrency?->code ?? 'VND',
                'accounts_count' => $accounts->count(),
            ],
            'recentTransactions' => $recentTransactions,
            'budgets' => $budgets,
            'spendingTrend' => $spendingTrend,
        ]);
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
