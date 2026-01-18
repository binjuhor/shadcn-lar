<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    Relations\BelongsTo,
    Relations\HasMany,
    SoftDeletes
};
use Modules\Finance\ValueObjects\Money;
use OwenIt\Auditing\Contracts\Auditable;

class SavingsGoal extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table = 'finance_savings_goals';

    protected $fillable = [
        'user_id',
        'target_account_id',
        'name',
        'description',
        'icon',
        'color',
        'target_amount',
        'current_amount',
        'currency_code',
        'target_date',
        'status',
        'is_active',
        'completed_at',
    ];

    protected $appends = ['progress_percent', 'remaining_amount'];

    protected function casts(): array
    {
        return [
            'target_amount' => 'float',
            'current_amount' => 'float',
            'target_date' => 'date',
            'is_active' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'target_account_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SavingsContribution::class);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount === 0) {
            return 0;
        }

        return min(($this->current_amount / $this->target_amount) * 100, 100);
    }

    public function getRemainingAmountAttribute(): int
    {
        return max($this->target_amount - $this->current_amount, 0);
    }

    public function getTargetMoney(): Money
    {
        return new Money($this->target_amount, $this->currency_code);
    }

    public function getCurrentMoney(): Money
    {
        return new Money($this->current_amount, $this->currency_code);
    }

    public function getRemainingMoney(): Money
    {
        return new Money($this->remaining_amount, $this->currency_code);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->is_active;
    }

    public function hasReachedTarget(): bool
    {
        return $this->current_amount >= $this->target_amount;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    protected static function newFactory()
    {
        return \Modules\Finance\Database\Factories\SavingsGoalFactory::new();
    }
}
