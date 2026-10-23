<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Events\SavingsGoalCompleted;
use Modules\Finance\Models\{Account, SavingsContribution, SavingsGoal, Transaction};

class SavingsGoalService
{
    public function __construct(
        private ExchangeRateService $exchangeRateService
    ) {}

    public function createGoal(array $data): SavingsGoal
    {
        return DB::transaction(function () use ($data) {
            $goal = SavingsGoal::create([
                'user_id' => $data['user_id'] ?? auth()->id(),
                'target_account_id' => $data['target_account_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'color' => $data['color'] ?? null,
                'target_amount' => $data['target_amount'],
                'current_amount' => 0,
                'currency_code' => $data['currency_code'],
                'target_date' => $data['target_date'] ?? null,
                'status' => 'active',
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncWithLinkedAccount($goal);

            return $goal;
        });
    }

    public function updateGoal(SavingsGoal $goal, array $data): SavingsGoal
    {
        return DB::transaction(function () use ($goal, $data) {
            $goal->update($data);

            if (array_key_exists('target_account_id', $data)) {
                $this->syncWithLinkedAccount($goal->fresh());
            }

            $this->checkCompletion($goal);

            return $goal->fresh();
        });
    }

    public function addContribution(SavingsGoal $goal, array $data): SavingsContribution
    {
        return DB::transaction(function () use ($goal, $data) {
            $contribution = SavingsContribution::create([
                'savings_goal_id' => $goal->id,
                'transaction_id' => $data['transaction_id'] ?? null,
                'amount' => abs($data['amount']),
                'currency_code' => $goal->currency_code,
                'contribution_date' => $data['contribution_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'type' => isset($data['transaction_id']) ? 'linked' : 'manual',
            ]);

            $this->recalculateProgress($goal);
            $this->checkCompletion($goal);

            return $contribution;
        });
    }

    public function withdrawContribution(SavingsGoal $goal, array $data): SavingsContribution
    {
        return DB::transaction(function () use ($goal, $data) {
            $withdrawAmount = abs($data['amount']);

            if ($withdrawAmount > $goal->current_amount) {
                throw new \Exception('Withdrawal amount exceeds current savings');
            }

            $contribution = SavingsContribution::create([
                'savings_goal_id' => $goal->id,
                'transaction_id' => $data['transaction_id'] ?? null,
                'amount' => -$withdrawAmount,
                'currency_code' => $goal->currency_code,
                'contribution_date' => $data['contribution_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'type' => isset($data['transaction_id']) ? 'linked' : 'manual',
            ]);

            $this->recalculateProgress($goal);

            if ($goal->status === 'completed' && ! $goal->hasReachedTarget()) {
                $goal->update([
                    'status' => 'active',
                    'completed_at' => null,
                ]);
            }

            return $contribution;
        });
    }

    public function recalculateProgress(SavingsGoal $goal): void
    {
        // When a linked account exists, the account balance is the source of truth
        if ($goal->target_account_id) {
            $this->syncWithLinkedAccount($goal);

            return;
        }

        $totalContributions = $goal->contributions()->sum('amount');
        $goal->update(['current_amount' => max($totalContributions, 0)]);
    }

    public function checkCompletion(SavingsGoal $goal): bool
    {
        $goal->refresh();

        if ($goal->hasReachedTarget() && $goal->status === 'active') {
            $goal->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            event(new SavingsGoalCompleted($goal));

            return true;
        }

        return false;
    }

    public function pauseGoal(SavingsGoal $goal): SavingsGoal
    {
        $goal->update(['status' => 'paused']);

        return $goal;
    }

    public function resumeGoal(SavingsGoal $goal): SavingsGoal
    {
        $goal->update(['status' => 'active']);
        $this->checkCompletion($goal);

        return $goal;
    }

    public function cancelGoal(SavingsGoal $goal): SavingsGoal
    {
        $goal->update(['status' => 'cancelled']);

        return $goal;
    }

    public function linkTransaction(SavingsGoal $goal, Transaction $transaction): SavingsContribution
    {
        if ($transaction->user_id !== $goal->user_id) {
            throw new \Exception('Transaction does not belong to user');
        }

        $amount = $transaction->amount;

        if ($transaction->currency_code !== $goal->currency_code) {
            $amount = $this->exchangeRateService->convert(
                $amount,
                $transaction->currency_code,
                $goal->currency_code,
                $transaction->account?->rate_source
            );
        }

        return $this->addContribution($goal, [
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'contribution_date' => $transaction->transaction_date,
            'notes' => "Linked from transaction: {$transaction->description}",
        ]);
    }

    /**
     * Transfer money from an account to a savings goal.
     * Creates a transaction and links it as a contribution.
     */
    public function transferToGoal(SavingsGoal $goal, array $data): SavingsContribution
    {
        return DB::transaction(function () use ($goal, $data) {
            $account = Account::findOrFail($data['from_account_id']);
            $goalAmount = abs($data['amount']); // amount in goal currency (what user entered)

            // Convert goal currency amount to account currency to know how much to deduct
            $accountAmount = $goalAmount;
            if ($account->currency_code !== $goal->currency_code) {
                $accountAmount = $this->exchangeRateService->convert(
                    $goalAmount,
                    $goal->currency_code,
                    $account->currency_code,
                    $account->rate_source
                );
            }

            // Transaction uses account's own currency
            $transaction = Transaction::create([
                'user_id' => $goal->user_id,
                'account_id' => $account->id,
                'category_id' => $data['category_id'] ?? null,
                'transaction_type' => 'expense',
                'amount' => $accountAmount,
                'currency_code' => $account->currency_code,
                'description' => $data['notes'] ?? "Transfer to savings: {$goal->name}",
                'transaction_date' => $data['transfer_date'] ?? now()->toDateString(),
            ]);

            $account->updateBalance(-$accountAmount);

            return $this->addContribution($goal, [
                'transaction_id' => $transaction->id,
                'amount' => $goalAmount, // contribution recorded in goal currency
                'contribution_date' => $transaction->transaction_date,
                'notes' => $data['notes'] ?? 'Transfer from account',
            ]);
        });
    }

    public function syncWithLinkedAccount(SavingsGoal $goal): void
    {
        if (! $goal->target_account_id) {
            return;
        }

        $account = Account::find($goal->target_account_id);

        if (! $account) {
            return;
        }

        $balance = max($account->current_balance, 0);

        if ($account->currency_code !== $goal->currency_code) {
            $balance = $this->exchangeRateService->convert(
                $balance,
                $account->currency_code,
                $goal->currency_code,
                $account->rate_source
            );
        }

        $goal->update(['current_amount' => $balance]);
    }

    public function unlinkContribution(SavingsContribution $contribution): void
    {
        DB::transaction(function () use ($contribution) {
            $goal = $contribution->savingsGoal;
            $contribution->forceDelete();
            $this->recalculateProgress($goal);

            if ($goal->status === 'completed' && ! $goal->hasReachedTarget()) {
                $goal->update([
                    'status' => 'active',
                    'completed_at' => null,
                ]);
            }
        });
    }
}
