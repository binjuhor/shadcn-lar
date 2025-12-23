# Phase 05: Controllers & API Resources

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 04 (services must exist)

## Overview
- Priority: high
- Status: pending
- Description: Create controllers following PostController patterns. Use Inertia::render for views. Create API resources for JSON transformation.

## Key Insights
From research: Controllers use `$this->authorize()`, return Inertia responses. Resources transform models with proper typing.

## Requirements
### Functional
- CRUD controllers for Account, Transaction, Budget, Goal, Category
- DashboardController with aggregated data
- API Resources for all models

### Non-functional
- Follow PostController patterns
- Use dependency injection for services
- Return paginated collections

## Related Code Files
### Files to Create
```
Modules/Mokey/Http/Controllers/
├── AccountController.php
├── TransactionController.php
├── BudgetController.php
├── GoalController.php
├── CategoryController.php
└── DashboardController.php

Modules/Mokey/Http/Resources/
├── AccountResource.php
├── TransactionResource.php
├── BudgetResource.php
├── GoalResource.php
├── CategoryResource.php
└── DashboardResource.php
```

## Implementation Steps

### 1. Create AccountController
```php
// Modules/Mokey/Http/Controllers/AccountController.php
namespace Modules\Mokey\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Mokey\Models\Account;
use Modules\Mokey\Models\Currency;
use Modules\Mokey\Http\Resources\AccountResource;
use Modules\Mokey\Http\Resources\CurrencyResource;
use Modules\Mokey\Http\Requests\StoreAccountRequest;
use Modules\Mokey\Http\Requests\UpdateAccountRequest;
use Modules\Mokey\Services\AccountService;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accountService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $query = Account::forUser(auth()->id())->with('currency');

        if ($request->filled('type')) {
            $query->where('account_type', $request->type);
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $accounts = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('mokey/accounts', [
            'accounts' => [
                'data' => AccountResource::collection($accounts->items())->resolve(),
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'total' => $accounts->total(),
            ],
            'filters' => $request->only(['type', 'active_only']),
            'currencies' => CurrencyResource::collection(Currency::active()->get())->resolve(),
            'account_types' => ['checking', 'savings', 'credit_card', 'cash', 'investment'],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Account::class);

        return Inertia::render('mokey/create-account', [
            'currencies' => CurrencyResource::collection(Currency::active()->get())->resolve(),
            'account_types' => ['checking', 'savings', 'credit_card', 'cash', 'investment'],
        ]);
    }

    public function store(StoreAccountRequest $request)
    {
        $this->authorize('create', Account::class);

        $data = $request->validated();
        $data['user_id'] = auth()->id();

        Account::create($data);

        return redirect()->route('dashboard.mokey.accounts.index')
            ->with('success', 'Account created successfully!');
    }

    public function show(Account $account): Response
    {
        $this->authorize('view', $account);

        $account->load(['transactions' => fn($q) => $q->latest('transaction_date')->limit(10)]);

        return Inertia::render('mokey/account', [
            'account' => AccountResource::make($account)->resolve(),
        ]);
    }

    public function edit(Account $account): Response
    {
        $this->authorize('update', $account);

        return Inertia::render('mokey/edit-account', [
            'account' => AccountResource::make($account)->resolve(),
            'currencies' => CurrencyResource::collection(Currency::active()->get())->resolve(),
            'account_types' => ['checking', 'savings', 'credit_card', 'cash', 'investment'],
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $this->authorize('update', $account);

        $account->update($request->validated());

        return redirect()->route('dashboard.mokey.accounts.index')
            ->with('success', 'Account updated successfully!');
    }

    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        $account->delete();

        return redirect()->route('dashboard.mokey.accounts.index')
            ->with('success', 'Account deleted successfully!');
    }
}
```

### 2. Create TransactionController
```php
// Modules/Mokey/Http/Controllers/TransactionController.php
namespace Modules\Mokey\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Mokey\Models\Transaction;
use Modules\Mokey\Models\Account;
use Modules\Mokey\Models\Category;
use Modules\Mokey\Http\Resources\TransactionResource;
use Modules\Mokey\Http\Resources\AccountResource;
use Modules\Mokey\Http\Resources\CategoryResource;
use Modules\Mokey\Http\Requests\StoreTransactionRequest;
use Modules\Mokey\Services\TransactionService;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $query = Transaction::where('user_id', auth()->id())
            ->with(['account', 'category', 'transferAccount'])
            ->latest('transaction_date');

        // Filters
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', "%{$request->search}%");
        }

        $transactions = $query->paginate(25)->withQueryString();

        return Inertia::render('mokey/transactions', [
            'transactions' => [
                'data' => TransactionResource::collection($transactions->items())->resolve(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
            'filters' => $request->only(['account_id', 'category_id', 'type', 'start_date', 'end_date', 'search']),
            'accounts' => AccountResource::collection(Account::forUser(auth()->id())->active()->get())->resolve(),
            'categories' => CategoryResource::collection(Category::active()->get())->resolve(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('mokey/create-transaction', [
            'accounts' => AccountResource::collection(Account::forUser(auth()->id())->active()->get())->resolve(),
            'categories' => CategoryResource::collection(Category::active()->get())->resolve(),
        ]);
    }

    public function store(StoreTransactionRequest $request)
    {
        $this->authorize('create', Transaction::class);

        $data = $request->validated();
        $data['user_id'] = auth()->id();

        $this->transactionService->create($data);

        return redirect()->route('dashboard.mokey.transactions.index')
            ->with('success', 'Transaction created successfully!');
    }

    public function show(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category', 'transferAccount']);

        return Inertia::render('mokey/transaction', [
            'transaction' => TransactionResource::make($transaction)->resolve(),
        ]);
    }

    public function edit(Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);

        return Inertia::render('mokey/edit-transaction', [
            'transaction' => TransactionResource::make($transaction)->resolve(),
            'accounts' => AccountResource::collection(Account::forUser(auth()->id())->active()->get())->resolve(),
            'categories' => CategoryResource::collection(Category::active()->get())->resolve(),
        ]);
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        $this->transactionService->update($transaction, $request->validated());

        return redirect()->route('dashboard.mokey.transactions.index')
            ->with('success', 'Transaction updated successfully!');
    }

    public function destroy(Transaction $transaction)
    {
        $this->authorize('delete', $transaction);

        $this->transactionService->delete($transaction);

        return redirect()->route('dashboard.mokey.transactions.index')
            ->with('success', 'Transaction deleted successfully!');
    }

    public function reconcile(Transaction $transaction)
    {
        $this->authorize('update', $transaction);

        $this->transactionService->reconcile($transaction);

        return back()->with('success', 'Transaction reconciled!');
    }
}
```

### 3. Create DashboardController
```php
// Modules/Mokey/Http/Controllers/DashboardController.php
namespace Modules\Mokey\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Mokey\Models\Account;
use Modules\Mokey\Models\Transaction;
use Modules\Mokey\Models\Budget;
use Modules\Mokey\Models\Goal;
use Modules\Mokey\Services\AccountService;
use Modules\Mokey\Services\BudgetService;

class DashboardController extends Controller
{
    public function __construct(
        private AccountService $accountService,
        private BudgetService $budgetService
    ) {}

    public function index(): Response
    {
        $userId = auth()->id();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Summary data
        $netWorth = $this->accountService->getNetWorth($userId);

        $monthlyIncome = Transaction::where('user_id', $userId)
            ->income()
            ->dateRange($startOfMonth, $endOfMonth)
            ->sum('base_amount');

        $monthlyExpenses = Transaction::where('user_id', $userId)
            ->expense()
            ->dateRange($startOfMonth, $endOfMonth)
            ->sum('base_amount');

        // Monthly trend (last 6 months)
        $monthlyTrend = collect(range(5, 0))->map(function ($i) use ($userId) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();

            return [
                'month' => $start->format('M'),
                'income' => Transaction::where('user_id', $userId)
                    ->income()->dateRange($start, $end)->sum('base_amount'),
                'expense' => Transaction::where('user_id', $userId)
                    ->expense()->dateRange($start, $end)->sum('base_amount'),
            ];
        });

        // Expense by category (this month)
        $expenseByCategory = Transaction::where('user_id', $userId)
            ->expense()
            ->dateRange($startOfMonth, $endOfMonth)
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(fn($txns) => [
                'category' => $txns->first()->category?->name ?? 'Uncategorized',
                'amount' => $txns->sum('base_amount'),
            ])
            ->values();

        // Active budgets with variance
        $activeBudgets = Budget::where('user_id', $userId)
            ->active()
            ->with('category')
            ->get()
            ->map(fn($budget) => [
                'budget' => $budget,
                'variance' => $this->budgetService->calculateVariance($budget),
            ]);

        // Active goals
        $activeGoals = Goal::where('user_id', $userId)
            ->where('is_active', true)
            ->where('is_completed', false)
            ->get()
            ->map(fn($goal) => [
                'id' => $goal->id,
                'name' => $goal->name,
                'target' => $goal->target_amount,
                'current' => $goal->current_amount,
                'percent' => $goal->target_amount > 0
                    ? round(($goal->current_amount / $goal->target_amount) * 100, 1)
                    : 0,
            ]);

        // Recent transactions
        $recentTransactions = Transaction::where('user_id', $userId)
            ->with(['account', 'category'])
            ->latest('transaction_date')
            ->limit(5)
            ->get();

        return Inertia::render('mokey/dashboard', [
            'summary' => [
                'net_worth' => $netWorth,
                'monthly_income' => $monthlyIncome,
                'monthly_expenses' => $monthlyExpenses,
                'monthly_savings' => $monthlyIncome - $monthlyExpenses,
            ],
            'monthly_trend' => $monthlyTrend,
            'expense_by_category' => $expenseByCategory,
            'active_budgets' => $activeBudgets,
            'active_goals' => $activeGoals,
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
```

### 4. Create API Resources
```php
// Modules/Mokey/Http/Resources/TransactionResource.php
namespace Modules\Mokey\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Mokey\ValueObjects\Money;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $money = new Money($this->amount, $this->currency_code);

        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'transfer_account_id' => $this->transfer_account_id,
            'transaction_type' => $this->transaction_type,
            'amount' => $this->amount,
            'amount_formatted' => $money->formatted(),
            'currency_code' => $this->currency_code,
            'description' => $this->description,
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'reconciled' => !is_null($this->reconciled_at),
            'account' => $this->whenLoaded('account', fn() => [
                'id' => $this->account->id,
                'name' => $this->account->name,
            ]),
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
            ]),
            'transfer_account' => $this->whenLoaded('transferAccount', fn() => [
                'id' => $this->transferAccount->id,
                'name' => $this->transferAccount->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### 5. Create BudgetController, GoalController, CategoryController
Follow same patterns as AccountController.

## Todo List
- [ ] Create AccountController with all CRUD methods
- [ ] Create TransactionController with filters and reconcile
- [ ] Create BudgetController with variance endpoint
- [ ] Create GoalController with progress endpoint
- [ ] Create CategoryController (nested tree support)
- [ ] Create DashboardController with aggregations
- [ ] Create all API Resources
- [ ] Add reconcile endpoint to TransactionController

## Success Criteria
- [ ] All CRUD operations work via Inertia
- [ ] Filters applied correctly on index pages
- [ ] Resources transform models with formatted amounts
- [ ] Dashboard aggregates data correctly

## Risk Assessment
- **Risk:** N+1 queries on relationships. **Mitigation:** Use eager loading (`with()`).
- **Risk:** Large datasets slow pagination. **Mitigation:** Add indexes, limit per_page.

## Security Considerations
- All controllers use authorize() for policy checks
- User ID taken from auth(), never from request
- Resources don't expose sensitive fields (account_number)

## Next Steps
Proceed to Phase 06: Policies & Authorization
