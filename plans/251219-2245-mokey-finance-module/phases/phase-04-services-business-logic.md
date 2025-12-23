# Phase 04: Services & Business Logic

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 03 (models must exist)

## Overview
- Priority: high
- Status: pending
- Description: Create service classes encapsulating business logic for transactions, budgets, goals, and exchange rates.

## Key Insights
From mokeyv2: TransactionService handles balance updates, transfers. BudgetService calculates variance. GoalService tracks progress snapshots.

## Requirements
### Functional
- TransactionService: create/update/delete with balance sync
- BudgetService: spent tracking, variance calculation
- GoalService: progress snapshots, completion checking
- ExchangeRateService: currency conversion

### Non-functional
- All services use DB transactions for consistency
- Services throw domain exceptions, not generic errors

## Related Code Files
### Files to Create
```
Modules/Mokey/Services/
├── TransactionService.php
├── BudgetService.php
├── GoalService.php
├── ExchangeRateService.php
└── AccountService.php

Modules/Mokey/Exceptions/
├── InsufficientBalanceException.php
└── InvalidTransferException.php
```

## Implementation Steps

### 1. Create TransactionService
```php
// Modules/Mokey/Services/TransactionService.php
namespace Modules\Mokey\Services;

use Illuminate\Support\Facades\DB;
use Modules\Mokey\Models\Account;
use Modules\Mokey\Models\Transaction;
use Modules\Mokey\Exceptions\InsufficientBalanceException;

class TransactionService
{
    public function __construct(
        private ExchangeRateService $exchangeRateService
    ) {}

    public function create(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            // Calculate base amount if different currency
            $data['base_amount'] = $this->calculateBaseAmount($data);

            $transaction = Transaction::create($data);
            $this->updateAccountBalance($transaction);

            if ($transaction->transaction_type === 'transfer') {
                $this->processTransfer($transaction);
            }

            // Update budget spent amount if expense
            if ($transaction->transaction_type === 'expense' && $transaction->category_id) {
                $this->updateBudgetSpent($transaction);
            }

            return $transaction;
        });
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            // Reverse old balance effect
            $this->reverseBalanceEffect($transaction);

            $data['base_amount'] = $this->calculateBaseAmount($data);
            $transaction->update($data);

            // Apply new balance effect
            $this->updateAccountBalance($transaction);

            return $transaction->fresh();
        });
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $this->reverseBalanceEffect($transaction);
            $transaction->delete();
        });
    }

    public function reconcile(Transaction $transaction): Transaction
    {
        $transaction->update(['reconciled_at' => now()]);
        return $transaction;
    }

    private function updateAccountBalance(Transaction $transaction): void
    {
        $account = $transaction->account;
        $amount = $transaction->amount;

        match ($transaction->transaction_type) {
            'income' => $account->increment('current_balance', $amount),
            'expense' => $account->decrement('current_balance', $amount),
            'transfer' => $account->decrement('current_balance', $amount),
        };
    }

    private function processTransfer(Transaction $transaction): void
    {
        if (!$transaction->transfer_account_id) {
            return;
        }

        $targetAccount = Account::find($transaction->transfer_account_id);
        $convertedAmount = $this->exchangeRateService->convert(
            $transaction->amount,
            $transaction->currency_code,
            $targetAccount->currency_code
        );

        $targetAccount->increment('current_balance', $convertedAmount);
    }

    private function reverseBalanceEffect(Transaction $transaction): void
    {
        $account = $transaction->account;
        $amount = $transaction->amount;

        match ($transaction->transaction_type) {
            'income' => $account->decrement('current_balance', $amount),
            'expense' => $account->increment('current_balance', $amount),
            'transfer' => $account->increment('current_balance', $amount),
        };

        if ($transaction->transaction_type === 'transfer' && $transaction->transfer_account_id) {
            $targetAccount = Account::find($transaction->transfer_account_id);
            $convertedAmount = $this->exchangeRateService->convert(
                $amount,
                $transaction->currency_code,
                $targetAccount->currency_code
            );
            $targetAccount->decrement('current_balance', $convertedAmount);
        }
    }

    private function calculateBaseAmount(array $data): int
    {
        if ($data['currency_code'] === config('mokey.default_currency')) {
            return $data['amount'];
        }

        return $this->exchangeRateService->convert(
            $data['amount'],
            $data['currency_code'],
            config('mokey.default_currency')
        );
    }

    private function updateBudgetSpent(Transaction $transaction): void
    {
        // Find active budget for category and update spent
        app(BudgetService::class)->updateSpentFromTransaction($transaction);
    }
}
```

### 2. Create BudgetService
```php
// Modules/Mokey/Services/BudgetService.php
namespace Modules\Mokey\Services;

use Modules\Mokey\Models\Budget;
use Modules\Mokey\Models\Transaction;

class BudgetService
{
    public function calculateVariance(Budget $budget): array
    {
        $variance = $budget->allocated_amount - $budget->spent_amount;
        $percentUsed = $budget->allocated_amount > 0
            ? ($budget->spent_amount / $budget->allocated_amount) * 100
            : 0;

        return [
            'allocated' => $budget->allocated_amount,
            'spent' => $budget->spent_amount,
            'remaining' => max(0, $variance),
            'variance' => $variance,
            'percent_used' => round($percentUsed, 2),
            'is_over_budget' => $variance < 0,
        ];
    }

    public function updateSpentFromTransaction(Transaction $transaction): void
    {
        $budget = Budget::where('user_id', $transaction->user_id)
            ->where('category_id', $transaction->category_id)
            ->where('is_active', true)
            ->where('start_date', '<=', $transaction->transaction_date)
            ->where('end_date', '>=', $transaction->transaction_date)
            ->first();

        if (!$budget) {
            return;
        }

        $this->recalculateSpent($budget);
    }

    public function recalculateSpent(Budget $budget): void
    {
        $spent = Transaction::where('user_id', $budget->user_id)
            ->where('category_id', $budget->category_id)
            ->where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$budget->start_date, $budget->end_date])
            ->sum('base_amount');

        $budget->update(['spent_amount' => $spent]);
    }
}
```

### 3. Create GoalService
```php
// Modules/Mokey/Services/GoalService.php
namespace Modules\Mokey\Services;

use Modules\Mokey\Models\Goal;
use Modules\Mokey\Models\GoalProgressSnapshot;

class GoalService
{
    public function updateProgress(Goal $goal, int $amount): Goal
    {
        $goal->update(['current_amount' => $amount]);

        if ($amount >= $goal->target_amount && !$goal->is_completed) {
            $goal->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);
        }

        return $goal->fresh();
    }

    public function createSnapshot(Goal $goal): GoalProgressSnapshot
    {
        $progressPercent = $goal->target_amount > 0
            ? ($goal->current_amount / $goal->target_amount) * 100
            : 0;

        $expectedProgress = $this->calculateExpectedProgress($goal);
        $variance = $goal->current_amount - $expectedProgress;

        return GoalProgressSnapshot::create([
            'goal_id' => $goal->id,
            'snapshot_date' => now()->toDateString(),
            'amount' => $goal->current_amount,
            'progress_percent' => round($progressPercent, 2),
            'on_track' => $variance >= 0,
            'variance_amount' => $variance,
        ]);
    }

    public function getProjectedCompletion(Goal $goal): ?string
    {
        if ($goal->current_amount >= $goal->target_amount) {
            return now()->toDateString();
        }

        $daysSinceStart = $goal->start_date->diffInDays(now());
        if ($daysSinceStart < 7 || $goal->current_amount <= 0) {
            return null; // Not enough data
        }

        $dailyRate = $goal->current_amount / $daysSinceStart;
        $remaining = $goal->target_amount - $goal->current_amount;
        $daysNeeded = (int) ceil($remaining / $dailyRate);

        return now()->addDays($daysNeeded)->toDateString();
    }

    private function calculateExpectedProgress(Goal $goal): int
    {
        if (!$goal->target_date) {
            return 0;
        }

        $totalDays = $goal->start_date->diffInDays($goal->target_date);
        $elapsedDays = $goal->start_date->diffInDays(now());

        if ($totalDays <= 0) {
            return $goal->target_amount;
        }

        $expectedPercent = min(1, $elapsedDays / $totalDays);
        return (int) ($goal->target_amount * $expectedPercent);
    }
}
```

### 4. Create ExchangeRateService
```php
// Modules/Mokey/Services/ExchangeRateService.php
namespace Modules\Mokey\Services;

use Modules\Mokey\Models\ExchangeRate;

class ExchangeRateService
{
    public function convert(int $amount, string $from, string $to): int
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getRate($from, $to);
        return (int) round($amount * $rate);
    }

    public function getRate(string $from, string $to, ?string $date = null): float
    {
        $date = $date ?? now()->toDateString();

        $rate = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();

        if ($rate) {
            return (float) $rate->rate;
        }

        // Try inverse rate
        $inverseRate = ExchangeRate::where('from_currency', $to)
            ->where('to_currency', $from)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();

        if ($inverseRate) {
            return 1 / (float) $inverseRate->rate;
        }

        return 1.0; // Fallback
    }

    public function storeRate(string $from, string $to, float $rate, ?string $date = null): ExchangeRate
    {
        return ExchangeRate::updateOrCreate(
            [
                'from_currency' => $from,
                'to_currency' => $to,
                'effective_date' => $date ?? now()->toDateString(),
            ],
            ['rate' => $rate]
        );
    }
}
```

### 5. Create AccountService
```php
// Modules/Mokey/Services/AccountService.php
namespace Modules\Mokey\Services;

use Modules\Mokey\Models\Account;
use Modules\Mokey\Models\AccountBalance;

class AccountService
{
    public function recordBalanceSnapshot(Account $account): AccountBalance
    {
        return AccountBalance::updateOrCreate(
            [
                'account_id' => $account->id,
                'recorded_at' => now()->toDateString(),
            ],
            ['balance' => $account->current_balance]
        );
    }

    public function getNetWorth(int $userId, string $baseCurrency = 'USD'): int
    {
        $exchangeService = app(ExchangeRateService::class);

        return Account::where('user_id', $userId)
            ->where('is_active', true)
            ->where('include_in_net_worth', true)
            ->get()
            ->sum(function ($account) use ($exchangeService, $baseCurrency) {
                return $exchangeService->convert(
                    $account->current_balance,
                    $account->currency_code,
                    $baseCurrency
                );
            });
    }
}
```

## Todo List
- [ ] Create TransactionService with balance sync
- [ ] Create BudgetService with variance calculation
- [ ] Create GoalService with progress tracking
- [ ] Create ExchangeRateService with conversion
- [ ] Create AccountService with net worth
- [ ] Create custom exceptions
- [ ] Register services in ServiceProvider if needed
- [ ] Write unit tests for services

## Success Criteria
- [ ] Transaction creation updates account balance correctly
- [ ] Transfers update both accounts
- [ ] Budget spent recalculates on transaction changes
- [ ] Goal progress snapshots created correctly
- [ ] Currency conversion works with stored rates

## Risk Assessment
- **Risk:** Race conditions on balance updates. **Mitigation:** Use DB transactions, consider locks for high-volume.
- **Risk:** Exchange rate not found. **Mitigation:** Return 1.0 fallback, log warning.

## Security Considerations
- Services must validate user ownership before operations
- All amounts validated as positive integers
- Transfer between same account prevented

## Next Steps
Proceed to Phase 05: Controllers & API Resources
