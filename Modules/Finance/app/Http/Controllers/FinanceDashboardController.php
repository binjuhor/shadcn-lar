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

class FinanceDashboardController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();

        $accounts = Account::where('user_id', $userId)
            ->where('is_active', true)
            ->where('exclude_from_total', false)
            ->get();

        $totalAssets = $accounts
            ->whereIn('account_type', ['bank', 'investment', 'cash'])
            ->where('current_balance', '>', 0)
            ->sum('current_balance');

        $totalLiabilities = abs($accounts
            ->whereIn('account_type', ['credit_card', 'loan'])
            ->sum('current_balance'));

        $netWorth = $totalAssets - $totalLiabilities;
        $totalBalance = $accounts->sum('current_balance');

        $defaultCurrency = Currency::where('is_default', true)->first();

        $recentTransactions = Transaction::with(['account', 'category', 'transferAccount'])
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
            ->orderBy('amount', 'desc')
            ->get();

        $spendingTrend = Transaction::select(
            DB::raw('DATE(transaction_date) as date'),
            DB::raw('SUM(amount) as amount')
        )
            ->whereHas('account', fn ($q) => $q->where('user_id', $userId))
            ->where('type', 'expense')
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
}
