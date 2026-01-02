<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Events\SavingsGoalCompleted;
use Modules\Finance\Models\SavingsContribution;
use Modules\Finance\Models\SavingsGoal;
use Modules\Finance\Models\Transaction;

class SavingsGoalService
{
    public function createGoal(array $data): SavingsGoal
    {
        return DB::transaction(function () use ($data) {
            return SavingsGoal::create([
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
        });
    }

    public function updateGoal(SavingsGoal $goal, array $data): SavingsGoal
    {
        return DB::transaction(function () use ($goal, $data) {
            $goal->update($data);
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

        return $this->addContribution($goal, [
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
            'contribution_date' => $transaction->transaction_date,
            'notes' => "Linked from transaction: {$transaction->description}",
        ]);
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
