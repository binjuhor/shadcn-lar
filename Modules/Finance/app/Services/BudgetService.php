<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\Budget;
use Modules\Finance\Models\Transaction;

class BudgetService
{
    public function createBudget(array $data): Budget
    {
        return DB::transaction(function () use ($data) {
            return Budget::create([
                'user_id' => $data['user_id'] ?? auth()->id(),
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'period_type' => $data['period_type'],
                'allocated_amount' => $data['amount'] ?? $data['allocated_amount'],
                'spent_amount' => 0,
                'currency_code' => $data['currency_code'] ?? 'USD',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $data['is_active'] ?? true,
                'rollover' => $data['rollover'] ?? false,
            ]);
        });
    }

    public function trackSpending(Budget $budget): void
    {
        $spent = Transaction::where('user_id', $budget->user_id)
            ->where('category_id', $budget->category_id)
            ->where('transaction_type', 'expense')
            ->whereBetween('transaction_date', [$budget->start_date, $budget->end_date])
            ->sum('amount');

        $budget->update(['spent_amount' => $spent]);
    }

    public function calculateVariance(Budget $budget): array
    {
        $variance = $budget->getVariance();
        $spentPercent = $budget->getSpentPercent();

        $status = 'on_track';
        if ($spentPercent >= 100) {
            $status = 'over_budget';
        } elseif ($spentPercent >= 80) {
            $status = 'warning';
        }

        return [
            'variance' => $variance,
            'spent_percent' => round($spentPercent, 2),
            'remaining' => $budget->allocated_amount - $budget->spent_amount,
            'status' => $status,
            'is_over_budget' => $budget->isOverBudget(),
        ];
    }

    public function getAlertThreshold(Budget $budget): ?string
    {
        $spentPercent = $budget->getSpentPercent();

        if ($spentPercent >= 100) {
            return 'critical';
        } elseif ($spentPercent >= 80) {
            return 'warning';
        }

        return null;
    }
}
