<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPeriod extends Model
{
    use HasFactory;

    protected $table = 'finance_plan_periods';

    protected $fillable = [
        'financial_plan_id',
        'year',
        'planned_income',
        'planned_expense',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'planned_income' => 'float',
            'planned_expense' => 'float',
        ];
    }

    public function financialPlan(): BelongsTo
    {
        return $this->belongsTo(FinancialPlan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlanItem::class, 'plan_period_id');
    }

    public function incomeItems(): HasMany
    {
        return $this->hasMany(PlanItem::class, 'plan_period_id')->where('type', 'income');
    }

    public function expenseItems(): HasMany
    {
        return $this->hasMany(PlanItem::class, 'plan_period_id')->where('type', 'expense');
    }

    public function getNetPlannedAttribute(): int
    {
        return $this->planned_income - $this->planned_expense;
    }

    public function recalculateTotals(): void
    {
        $this->planned_income = $this->calculateYearlyTotal('income');
        $this->planned_expense = $this->calculateYearlyTotal('expense');
        $this->save();
    }

    protected function calculateYearlyTotal(string $type): int
    {
        $items = $this->items()->where('type', $type)->get();

        return (int) $items->sum(function ($item) {
            return $this->getYearlyAmount($item->planned_amount, $item->recurrence);
        });
    }

    protected function getYearlyAmount(float $amount, string $recurrence): float
    {
        return match ($recurrence) {
            'monthly' => $amount * 12,
            'quarterly' => $amount * 4,
            'yearly', 'one_time' => $amount,
            default => $amount,
        };
    }
}
