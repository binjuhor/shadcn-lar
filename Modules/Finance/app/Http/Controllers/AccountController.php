<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Currency;

class AccountController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $userId = auth()->id();
        $accounts = Account::with('currency')
            ->where('user_id', $userId)
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        $activeAccounts = $accounts->where('exclude_from_total', false);

        $totalAssets = $activeAccounts
            ->whereIn('account_type', ['bank', 'investment', 'cash'])
            ->where('balance', '>', 0)
            ->sum('balance');

        $totalLiabilities = abs($activeAccounts
            ->whereIn('account_type', ['credit_card', 'loan'])
            ->sum('balance'));

        $netWorth = $totalAssets - $totalLiabilities;

        $defaultCurrency = Currency::where('is_default', true)->first();
        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::accounts/index', [
            'accounts' => $accounts,
            'summary' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'net_worth' => $netWorth,
                'currency_code' => $defaultCurrency?->code ?? 'VND',
            ],
            'currencies' => $currencies,
        ]);
    }

    public function create(): Response
    {
        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::accounts/create', [
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:bank,credit_card,investment,cash,loan,other'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'initial_balance' => ['required', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['boolean'],
            'exclude_from_total' => ['boolean'],
        ]);

        Account::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'currency_code' => $validated['currency_code'],
            'balance' => $validated['initial_balance'],
            'initial_balance' => $validated['initial_balance'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'exclude_from_total' => $validated['exclude_from_total'] ?? false,
        ]);

        return Redirect::route('dashboard.finance.accounts.index')
            ->with('success', 'Account created successfully');
    }

    public function show(Account $account): Response
    {
        $this->authorize('view', $account);

        $account->load(['currency', 'transactions' => function ($query) {
            $query->with(['category'])
                ->orderBy('transaction_date', 'desc')
                ->limit(50);
        }]);

        $totalIncome = $account->transactions()
            ->where('type', 'income')
            ->sum('amount');

        $totalExpense = $account->transactions()
            ->where('type', 'expense')
            ->sum('amount');

        $totalTransactions = $account->transactions()->count();

        return Inertia::render('Finance::accounts/show', [
            'account' => $account,
            'statistics' => [
                'total_transactions' => $totalTransactions,
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_change' => $totalIncome - $totalExpense,
            ],
        ]);
    }

    public function edit(Account $account): Response
    {
        $this->authorize('update', $account);

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::accounts/edit', [
            'account' => $account,
            'currencies' => $currencies,
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'account_type' => ['sometimes', 'in:bank,credit_card,investment,cash,loan,other'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'initial_balance' => ['sometimes', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
            'is_active' => ['sometimes', 'boolean'],
            'exclude_from_total' => ['sometimes', 'boolean'],
        ]);

        $account->update($validated);

        return Redirect::back()->with('success', 'Account updated successfully');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        if ($account->transactions()->exists()) {
            return Redirect::back()
                ->withErrors(['error' => 'Cannot delete account with existing transactions. Please delete transactions first.']);
        }

        $account->delete();

        return Redirect::route('dashboard.finance.accounts.index')
            ->with('success', 'Account deleted successfully');
    }
}
