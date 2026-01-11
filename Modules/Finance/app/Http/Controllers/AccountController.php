<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Http\Requests\Account\StoreAccountRequest;
use Modules\Finance\Http\Requests\Account\UpdateAccountRequest;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Currency;
use Modules\Finance\Services\ExchangeRateService;

class AccountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function index(): Response
    {
        $userId = auth()->id();
        $defaultCurrency = Currency::where('is_default', true)->first();
        $defaultCode = $defaultCurrency?->code ?? 'VND';

        $accounts = Account::with('currency')
            ->where('user_id', $userId)
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        $activeAccounts = $accounts->where('exclude_from_total', false);

        $totalAssets = $activeAccounts
            ->whereIn('account_type', ['bank', 'investment', 'cash'])
            ->where('current_balance', '>', 0)
            ->sum(fn ($account) => $this->convertToDefault($account->current_balance, $account->currency_code, $defaultCode));

        $totalLiabilities = abs($activeAccounts
            ->whereIn('account_type', ['credit_card', 'loan'])
            ->sum(fn ($account) => $this->convertToDefault($account->current_balance, $account->currency_code, $defaultCode)));

        $netWorth = $totalAssets - $totalLiabilities;

        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::accounts/index', [
            'accounts' => $accounts,
            'summary' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'net_worth' => $netWorth,
                'currency_code' => $defaultCode,
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

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $account = Account::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'currency_code' => $validated['currency_code'],
            'rate_source' => $validated['rate_source'] ?? null,
            'initial_balance' => $validated['initial_balance'],
            'current_balance' => $validated['initial_balance'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'exclude_from_total' => $validated['exclude_from_total'] ?? false,
        ]);

        if ($validated['is_default_payment'] ?? false) {
            $account->setAsDefaultPayment();
        }

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
            ->where('transaction_type', 'income')
            ->sum('amount');

        $totalExpense = $account->transactions()
            ->where('transaction_type', 'expense')
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

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $validated = $request->validated();

        if (isset($validated['initial_balance'])) {
            $validated['current_balance'] = $validated['initial_balance'];
        }

        $setAsDefault = $validated['is_default_payment'] ?? false;
        unset($validated['is_default_payment']);

        $account->update($validated);

        if ($setAsDefault) {
            $account->setAsDefaultPayment();
        } elseif ($account->is_default_payment && ! $setAsDefault) {
            $account->update(['is_default_payment' => false]);
        }

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

    protected function convertToDefault(float $amount, string $fromCurrency, string $defaultCurrency): float
    {
        if ($fromCurrency === $defaultCurrency) {
            return $amount;
        }

        try {
            return $this->exchangeRateService->convert($amount, $fromCurrency, $defaultCurrency);
        } catch (\Exception) {
            return $amount;
        }
    }
}
