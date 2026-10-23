<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Exceptions\ExchangeRateNotFoundException;
use Modules\Finance\Models\{Account, Budget, Category, RecurringTransaction, SavingsGoal, Transaction};

class FinancialToolService
{
    private const CASH_FLOW_MAX_MONTHS = 36;

    public function __construct(
        private TransactionService $transactions,
        private BudgetService $budgets,
        private SavingsGoalService $goals,
        private CashFlowBreakdownService $cashFlow,
        private RecurringTransactionService $recurring,
        private UserCurrencyConversionService $conversion,
    ) {}

    /** @return array<string, mixed> */
    public function getFinancialSummary(int $userId, array $filters = []): array
    {
        [$start, $end] = $this->period($filters);
        $totals = Transaction::query()
            ->where('user_id', $userId)
            ->whereBetween('transaction_date', [$start, $end])
            ->whereIn('transaction_type', ['income', 'expense'])
            ->selectRaw('currency_code, transaction_type, SUM(amount) as total')
            ->groupBy('currency_code', 'transaction_type')
            ->get()
            ->groupBy('currency_code');

        $cashFlow = $totals->map(function ($rows, string $currency): array {
            $income = (float) ($rows->firstWhere('transaction_type', 'income')?->total ?? 0);
            $expense = (float) ($rows->firstWhere('transaction_type', 'expense')?->total ?? 0);

            return compact('currency', 'income', 'expense') + ['net_cash_flow' => $income - $expense];
        })->values()->all();

        return [
            'period' => ['start' => $start, 'end' => $end],
            'cash_flow_by_currency' => $cashFlow,
            'account_balances' => $this->getAccountBalances($userId),
            'active_budgets' => Budget::where('user_id', $userId)->where('is_active', true)->count(),
            'active_goals' => SavingsGoal::where('user_id', $userId)->active()->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function getTransactions(int $userId, array $filters = []): array
    {
        $query = $this->transactionQuery($userId, $filters);
        $limit = min(max((int) ($filters['limit'] ?? 20), 1), 50);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $paginator = $query->latest('transaction_date')->latest('id')->paginate($limit, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())->map(fn (Transaction $transaction) => $this->transactionData($transaction))->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function searchTransactions(int $userId, array $filters): array
    {
        return $this->getTransactions($userId, $filters);
    }

    /** @return array<string, mixed> */
    public function createTransaction(int $userId, array $data): array
    {
        $account = Account::where('user_id', $userId)->findOrFail($data['account_id']);
        $this->ownedCategory($userId, $data['category_id'] ?? null);
        $data['user_id'] = $userId;
        $data['account_id'] = $account->id;

        $transaction = $data['transaction_type'] === 'income'
            ? $this->transactions->recordIncome($data)
            : $this->transactions->recordExpense($data);

        return $this->transactionData($transaction->loadMissing(['account', 'category']));
    }

    /** @return array<string, mixed> */
    public function updateTransaction(int $userId, array $data): array
    {
        $transaction = Transaction::where('user_id', $userId)->findOrFail($data['transaction_id']);
        if ($transaction->isTransfer()) {
            throw ValidationException::withMessages([
                'transaction_id' => 'Transfer transactions cannot be changed through this tool.',
            ]);
        }

        $this->ownedCategory($userId, $data['category_id'] ?? null);

        return DB::transaction(function () use ($transaction, $data): array {
            $oldImpact = $transaction->balanceImpact();
            $oldAccount = $transaction->account()->lockForUpdate()->firstOrFail();

            if (isset($data['account_id']) && (int) $data['account_id'] !== (int) $oldAccount->id) {
                $newAccount = Account::where('user_id', $transaction->user_id)->lockForUpdate()->findOrFail($data['account_id']);
                $oldAccount->updateBalance(-$oldImpact);
                $transaction->account_id = $newAccount->id;
                $transaction->currency_code = $newAccount->currency_code;
                $account = $newAccount;
            } else {
                $account = $oldAccount;
            }

            $transaction->fill(collect($data)->only([
                'category_id', 'transaction_type', 'amount', 'description', 'notes', 'transaction_date',
            ])->all());
            $transaction->save();

            if ($account->is($oldAccount)) {
                $account->updateBalance($transaction->balanceImpact() - $oldImpact);
            } else {
                $account->updateBalance($transaction->balanceImpact());
            }

            return $this->transactionData($transaction->fresh(['account', 'category']));
        });
    }

    /** @return array<string, mixed> */
    public function createTransfer(int $userId, array $data): array
    {
        if ((int) $data['from_account_id'] === (int) $data['to_account_id']) {
            throw ValidationException::withMessages(['to_account_id' => 'Choose two different accounts.']);
        }

        try {
            $result = $this->transactions->recordTransfer($data + ['user_id' => $userId]);
        } catch (ExchangeRateNotFoundException $exception) {
            throw ValidationException::withMessages(['to_account_id' => $exception->getMessage()]);
        }

        return [
            'from' => $this->transactionData($result['debit']->loadMissing(['account', 'category'])),
            'to' => $this->transactionData($result['credit']->loadMissing(['account', 'category'])),
            'exchange_rate' => $result['exchange_rate'],
            'converted_amount' => (float) $result['converted_amount'],
        ];
    }

    /**
     * Passive vs active income and essential vs other expense, in the user's
     * default currency. Defaults to the current month.
     *
     * @return array<string, mixed>
     */
    public function getCashFlowBreakdown(int $userId, array $filters = []): array
    {
        $start = Carbon::parse($filters['start_date'] ?? now()->startOfMonth());
        $end = Carbon::parse($filters['end_date'] ?? now());

        if ($start->copy()->addMonths(self::CASH_FLOW_MAX_MONTHS)->lt($end)) {
            throw ValidationException::withMessages(['start_date' => 'The period can be at most '.self::CASH_FLOW_MAX_MONTHS.' months.']);
        }
        $currency = $this->conversion->defaultCurrency(User::findOrFail($userId));
        $result = $this->cashFlow->breakdown($userId, $start, $end, $currency);

        // A single-month question does not need the per-month rows
        if (($filters['group_by'] ?? 'total') === 'total') {
            unset($result['monthly_data']);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function getRecurringTransactions(int $userId, array $filters = []): array
    {
        $days = min(max((int) ($filters['days'] ?? 30), 1), 90);
        $currency = $this->conversion->defaultCurrency(User::findOrFail($userId));

        return [
            'upcoming_days' => $days,
            'upcoming' => $this->recurring->getUpcoming($userId, $days)->take(50)->map(fn (RecurringTransaction $item) => [
                'id' => $item->id,
                'name' => $item->name ?? $item->description,
                'type' => $item->transaction_type,
                'amount' => (float) $item->amount,
                'currency' => $item->currency_code,
                'frequency' => $item->frequency,
                'next_run_date' => $item->next_run_date?->toDateString(),
                'account' => $item->account?->name,
                'category' => $item->category?->name,
                'passive' => (bool) $item->category?->is_passive,
            ])->values()->all(),
            'monthly_projection' => $this->recurring->getMonthlyProjection($userId, $currency),
        ];
    }

    /** @return array<string, mixed> */
    public function categorizeTransaction(int $userId, array $data): array
    {
        return $this->updateTransaction($userId, $data);
    }

    /** @return array<string, mixed> */
    public function getCategories(int $userId, array $filters = []): array
    {
        $items = Category::userCategories($userId)
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->byType($type))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'type', 'icon', 'color', 'user_id']);

        return ['items' => $items->toArray()];
    }

    /** @return array<string, mixed> */
    public function getBudgets(int $userId, array $filters = []): array
    {
        $items = Budget::with('category')->where('user_id', $userId)
            ->when(array_key_exists('active', $filters), fn (Builder $query) => $query->where('is_active', (bool) $filters['active']))
            ->orderByDesc('start_date')->get();

        return ['items' => $items->map(fn (Budget $budget) => $budget->toArray() + ['status' => $this->budgets->calculateVariance($budget)])->all()];
    }

    /** @return array<string, mixed> */
    public function createBudget(int $userId, array $data): array
    {
        $this->ownedCategory($userId, $data['category_id'] ?? null);
        $budget = $this->budgets->createBudget($data + ['user_id' => $userId]);

        return $budget->fresh('category')->toArray();
    }

    /** @return array<string, mixed> */
    public function updateBudget(int $userId, array $data): array
    {
        return DB::transaction(function () use ($userId, $data): array {
            $budget = Budget::where('user_id', $userId)->lockForUpdate()->findOrFail($data['budget_id']);
            $this->ownedCategory($userId, $data['category_id'] ?? null);
            $budget->update(collect($data)->except(['budget_id'])->all());
            $this->budgets->trackSpending($budget);

            return $budget->fresh('category')->toArray();
        });
    }

    /** @return array<string, mixed> */
    public function suggestBudget(int $userId, array $filters = []): array
    {
        $months = min(max((int) ($filters['months'] ?? 3), 1), 12);
        $start = now()->subMonths($months)->startOfMonth();
        $rows = Transaction::where('user_id', $userId)->where('transaction_type', 'expense')
            ->where('transaction_date', '>=', $start)->whereNotNull('category_id')
            ->selectRaw('category_id, currency_code, SUM(amount) / ? as monthly_average', [$months])
            ->groupBy('category_id', 'currency_code')->with('category:id,name')->get();

        return ['basis_months' => $months, 'suggestions' => $rows->map(fn ($row) => [
            'category_id' => $row->category_id,
            'category' => $row->category?->name,
            'currency' => $row->currency_code,
            'suggested_amount' => round((float) $row->monthly_average * 0.95, 2),
            'method' => '95% of historical monthly average',
        ])->all()];
    }

    /** @return array<string, mixed> */
    public function getGoals(int $userId, array $filters = []): array
    {
        $items = SavingsGoal::with('targetAccount')->where('user_id', $userId)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('target_date')->get();

        return ['items' => $items->toArray()];
    }

    /** @return array<string, mixed> */
    public function createGoal(int $userId, array $data): array
    {
        if (isset($data['target_account_id'])) {
            Account::where('user_id', $userId)->findOrFail($data['target_account_id']);
        }

        return $this->goals->createGoal($data + ['user_id' => $userId])->toArray();
    }

    /** @return array<string, mixed> */
    public function suggestSavingPlan(int $userId, array $filters = []): array
    {
        $goals = SavingsGoal::where('user_id', $userId)->active()->get();

        return ['suggestions' => $goals->map(function (SavingsGoal $goal): array {
            $months = $goal->target_date ? max(now()->diffInMonths($goal->target_date, false), 1) : 12;

            return [
                'goal_id' => $goal->id,
                'name' => $goal->name,
                'currency' => $goal->currency_code,
                'remaining_amount' => $goal->remaining_amount,
                'monthly_amount' => round($goal->remaining_amount / $months, 2),
                'months' => $months,
            ];
        })->all()];
    }

    /** @return array<string, mixed> */
    public function getAccounts(int $userId, array $filters = []): array
    {
        return ['items' => Account::where('user_id', $userId)
            ->when(array_key_exists('active', $filters), fn (Builder $query) => $query->where('is_active', (bool) $filters['active']))
            ->orderBy('name')->get()->map(fn (Account $account) => $this->accountData($account))->all()];
    }

    /** @return array<string, mixed> */
    public function getAccountBalances(int $userId, array $filters = []): array
    {
        $accounts = Account::where('user_id', $userId)->where('is_active', true)->orderBy('name')->get();

        return [
            'accounts' => $accounts->map(fn (Account $account) => $this->accountData($account))->all(),
            'totals_by_currency' => $accounts->where('exclude_from_total', false)->groupBy('currency_code')
                ->map(fn ($items, string $currency) => ['currency' => $currency, 'balance' => (float) $items->sum('current_balance')])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function comparePeriods(int $userId, array $filters): array
    {
        $first = $this->periodTotals($userId, $filters['first_start'], $filters['first_end']);
        $second = $this->periodTotals($userId, $filters['second_start'], $filters['second_end']);

        return ['first_period' => $first, 'second_period' => $second, 'note' => 'Amounts remain grouped by currency.'];
    }

    /** @return array<string, mixed> */
    public function analyzeSpendingPattern(int $userId, array $filters = []): array
    {
        [$start, $end] = $this->period($filters, 90);
        $rows = Transaction::where('user_id', $userId)->where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])->whereNotNull('category_id')
            ->selectRaw('category_id, currency_code, COUNT(*) as transaction_count, SUM(amount) as total, AVG(amount) as average')
            ->groupBy('category_id', 'currency_code')->with('category:id,name')->orderByDesc('total')->get();

        return ['period' => compact('start', 'end'), 'categories' => $rows->map(fn ($row) => [
            'category' => $row->category?->name,
            'currency' => $row->currency_code,
            'transaction_count' => (int) $row->transaction_count,
            'total' => (float) $row->total,
            'average' => round((float) $row->average, 2),
        ])->all()];
    }

    /** @return array<string, mixed> */
    public function forecastCashFlow(int $userId, array $filters = []): array
    {
        $months = min(max((int) ($filters['history_months'] ?? 3), 1), 12);
        $horizon = min(max((int) ($filters['forecast_months'] ?? 3), 1), 12);
        $start = now()->subMonths($months)->startOfMonth()->toDateString();
        $totals = $this->periodTotals($userId, $start, now()->toDateString());

        return [
            'history_months' => $months,
            'forecast_months' => $horizon,
            'method' => 'Historical monthly average; recurring changes and market effects are not modeled.',
            'forecast_by_currency' => collect($totals['by_currency'])->map(fn (array $row) => [
                'currency' => $row['currency'],
                'monthly_income' => round($row['income'] / $months, 2),
                'monthly_expense' => round($row['expense'] / $months, 2),
                'monthly_net' => round($row['net_cash_flow'] / $months, 2),
                'projected_net' => round(($row['net_cash_flow'] / $months) * $horizon, 2),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function generateMonthlyReport(int $userId, array $filters = []): array
    {
        $month = CarbonImmutable::createFromFormat('Y-m', $filters['month'] ?? now()->format('Y-m'))->startOfMonth();

        return [
            'month' => $month->format('Y-m'),
            'summary' => $this->getFinancialSummary($userId, ['start_date' => $month->toDateString(), 'end_date' => $month->endOfMonth()->toDateString()]),
            'spending_pattern' => $this->analyzeSpendingPattern($userId, ['start_date' => $month->toDateString(), 'end_date' => $month->endOfMonth()->toDateString()]),
        ];
    }

    private function transactionQuery(int $userId, array $filters): Builder
    {
        return Transaction::with(['account:id,name', 'category:id,name'])->where('user_id', $userId)
            ->when($filters['query'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $nested) use ($search): void {
                $nested->where('description', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%");
            }))
            ->when($filters['account_id'] ?? null, fn (Builder $query, int $id) => $query->where('account_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, string $type) => $query->where('transaction_type', $type))
            ->when($filters['start_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date));
    }

    private function ownedCategory(int $userId, mixed $categoryId): ?Category
    {
        if (! $categoryId) {
            return null;
        }

        return Category::where(fn (Builder $query) => $query->where('user_id', $userId)->orWhereNull('user_id'))->findOrFail($categoryId);
    }

    /** @return array{0: string, 1: string} */
    private function period(array $filters, int $defaultDays = 30): array
    {
        return [
            $filters['start_date'] ?? now()->subDays($defaultDays - 1)->toDateString(),
            $filters['end_date'] ?? now()->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function periodTotals(int $userId, string $start, string $end): array
    {
        $rows = Transaction::where('user_id', $userId)->whereBetween('transaction_date', [$start, $end])
            ->whereIn('transaction_type', ['income', 'expense'])
            ->selectRaw('currency_code, transaction_type, SUM(amount) as total')
            ->groupBy('currency_code', 'transaction_type')->get()->groupBy('currency_code');

        return ['start' => $start, 'end' => $end, 'by_currency' => $rows->map(function ($items, string $currency): array {
            $income = (float) ($items->firstWhere('transaction_type', 'income')?->total ?? 0);
            $expense = (float) ($items->firstWhere('transaction_type', 'expense')?->total ?? 0);

            return compact('currency', 'income', 'expense') + ['net_cash_flow' => $income - $expense];
        })->values()->all()];
    }

    /** @return array<string, mixed> */
    private function transactionData(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'account_id' => $transaction->account_id,
            'account' => $transaction->account?->name,
            'category_id' => $transaction->category_id,
            'category' => $transaction->category?->name,
            'type' => $transaction->transaction_type,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency_code,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'date' => $transaction->transaction_date?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function accountData(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->account_type,
            'balance' => (float) $account->current_balance,
            'currency' => $account->currency_code,
            'active' => $account->is_active,
            'excluded_from_total' => $account->exclude_from_total,
        ];
    }
}
