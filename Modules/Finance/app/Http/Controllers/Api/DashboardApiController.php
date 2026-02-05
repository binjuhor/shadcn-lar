<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\{Account, Budget, Currency, RecurringTransaction, Transaction};
use Modules\Finance\Services\{ExchangeRateService, RecurringTransactionService};

class DashboardApiController extends Controller
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService,
        protected RecurringTransactionService $recurringService
    ) {}

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $userId = $user->id;

        $userSettings = $user->finance_settings ?? [];
        $defaultCode = $userSettings['default_currency'] ?? Currency::where('is_default', true)->first()?->code ?? 'VND';

        $allAccounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $includedAccounts = $allAccounts->where('exclude_from_total', false);

        $totalAssets = $includedAccounts
            ->where('has_credit_limit', false)
            ->where('current_balance', '>', 0)
            ->sum(fn ($account) => $this->convertToDefault($account->current_balance, $account->currency_code, $defaultCode, $account->rate_source));

        $totalLiabilities = $allAccounts
            ->where('has_credit_limit', true)
            ->sum(function ($account) use ($defaultCode) {
                $amountOwed = $account->initial_balance - $account->current_balance;
                if ($amountOwed <= 0) {
                    return 0;
                }

                return $this->convertToDefault($amountOwed, $account->currency_code, $defaultCode, $account->rate_source);
            });

        $netWorth = $totalAssets - $totalLiabilities;
        $totalBalance = $includedAccounts->sum(fn ($account) => $this->convertToDefault($account->current_balance, $account->currency_code, $defaultCode, $account->rate_source));

        $recentTransactions = Transaction::with(['account', 'category'])
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'transaction_type' => $tx->transaction_type,
                'amount' => (float) $tx->amount,
                'currency_code' => $tx->currency_code,
                'description' => $tx->description,
                'transaction_date' => $tx->transaction_date->toDateString(),
                'account' => $tx->account ? [
                    'id' => $tx->account->id,
                    'name' => $tx->account->name,
                ] : null,
                'category' => $tx->category ? [
                    'id' => $tx->category->id,
                    'name' => $tx->category->name,
                    'color' => $tx->category->color,
                    'icon' => $tx->category->icon,
                ] : null,
            ]);

        $currentDate = now();
        $budgets = Budget::with('category')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate)
            ->orderBy('allocated_amount', 'desc')
            ->get()
            ->map(fn ($budget) => [
                'id' => $budget->id,
                'name' => $budget->name,
                'allocated_amount' => (float) $budget->allocated_amount,
                'spent_amount' => (float) $budget->spent_amount,
                'remaining_amount' => (float) $budget->remaining_amount,
                'progress' => $budget->progress,
                'currency_code' => $budget->currency_code,
                'category' => $budget->category ? [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                    'color' => $budget->category->color,
                ] : null,
            ]);

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
                'amount' => (float) $item->amount,
            ]);

        $recurringProjection = $this->recurringService->getMonthlyProjection($userId, $defaultCode);

        $upcomingRecurrings = RecurringTransaction::forUser($userId)
            ->active()
            ->upcoming(7)
            ->with(['account', 'category'])
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'transaction_type' => $r->transaction_type,
                'amount' => (int) $r->amount,
                'currency_code' => $r->currency_code,
                'frequency' => $r->frequency,
                'next_run_date' => $r->next_run_date->toDateString(),
                'category' => $r->category ? [
                    'name' => $r->category->name,
                    'color' => $r->category->color,
                    'is_passive' => $r->category->is_passive,
                ] : null,
            ]);

        return response()->json([
            'data' => [
                'summary' => [
                    'total_assets' => $totalAssets,
                    'total_liabilities' => $totalLiabilities,
                    'net_worth' => $netWorth,
                    'total_balance' => $totalBalance,
                    'currency_code' => $defaultCode,
                    'accounts_count' => $allAccounts->count(),
                ],
                'recent_transactions' => $recentTransactions,
                'budgets' => $budgets,
                'spending_trend' => $spendingTrend,
                'recurring_projection' => $recurringProjection,
                'upcoming_recurrings' => $upcomingRecurrings,
            ],
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
