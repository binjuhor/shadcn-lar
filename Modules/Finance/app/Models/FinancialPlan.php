<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    Relations\BelongsTo,
    Relations\HasMany
};

class FinancialPlan extends Model
{
    use HasFactory;

    protected $table = 'finance_plans';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'start_year',
        'end_year',
        'currency_code',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(PlanPeriod::class, 'financial_plan_id')->orderBy('year');
    }

    public function getTotalPlannedIncomeAttribute(): int
    {
        return $this->periods->sum('planned_income');
    }

    public function getTotalPlannedExpenseAttribute(): int
    {
        return $this->periods->sum('planned_expense');
    }

    public function getNetPlannedAttribute(): int
    {
        return $this->total_planned_income - $this->total_planned_expense;
    }

    public function getYearSpanAttribute(): int
    {
        return $this->end_year - $this->start_year + 1;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
