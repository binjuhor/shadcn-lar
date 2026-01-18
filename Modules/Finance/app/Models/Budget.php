<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    Relations\BelongsTo
};
use Modules\Finance\ValueObjects\Money;

class Budget extends Model
{
    use HasFactory;

    protected $table = 'finance_budgets';

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'period_type',
        'allocated_amount',
        'spent_amount',
        'currency_code',
        'start_date',
        'end_date',
        'is_active',
        'rollover',
    ];

    protected $appends = ['amount', 'spent'];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'float',
            'spent_amount' => 'float',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'rollover' => 'boolean',
        ];
    }

    public function getAmountAttribute(): float
    {
        return (float) $this->allocated_amount;
    }

    public function getSpentAttribute(): float
    {
        return (float) $this->spent_amount;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function getAllocatedMoney(): Money
    {
        return new Money($this->allocated_amount, $this->currency_code);
    }

    public function getSpentMoney(): Money
    {
        return new Money($this->spent_amount, $this->currency_code);
    }

    public function getRemainingMoney(): Money
    {
        return new Money($this->allocated_amount - $this->spent_amount, $this->currency_code);
    }

    public function getSpentPercent(): float
    {
        if ($this->allocated_amount === 0) {
            return 0;
        }

        return ($this->spent_amount / $this->allocated_amount) * 100;
    }

    public function isOverBudget(): bool
    {
        return $this->spent_amount > $this->allocated_amount;
    }

    public function getVariance(): int
    {
        return $this->allocated_amount - $this->spent_amount;
    }

    public function scopeByPeriod($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    public function scopeActive($query)
    {
        return $query->where('end_date', '>=', now());
    }

    protected static function newFactory()
    {
        return \Modules\Finance\Database\Factories\BudgetFactory::new();
    }
}
