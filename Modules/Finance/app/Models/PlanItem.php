<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanItem extends Model
{
    use HasFactory;

    protected $table = 'finance_plan_items';

    protected $fillable = [
        'plan_period_id',
        'category_id',
        'name',
        'type',
        'planned_amount',
        'recurrence',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'float',
        ];
    }

    public function planPeriod(): BelongsTo
    {
        return $this->belongsTo(PlanPeriod::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getEffectiveAmountAttribute(): int
    {
        return match ($this->recurrence) {
            'monthly' => $this->planned_amount * 12,
            'quarterly' => $this->planned_amount * 4,
            'yearly', 'one_time' => $this->planned_amount,
            default => $this->planned_amount,
        };
    }

    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    protected static function booted(): void
    {
        static::saved(function (PlanItem $item) {
            $item->planPeriod?->recalculateTotals();
        });

        static::deleted(function (PlanItem $item) {
            $item->planPeriod?->recalculateTotals();
        });
    }
}
