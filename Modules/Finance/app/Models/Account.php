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

class Account extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table = 'finance_accounts';

    protected $fillable = [
        'user_id',
        'account_type',
        'has_credit_limit',
        'name',
        'currency_code',
        'rate_source',
        'account_number',
        'institution_name',
        'description',
        'initial_balance',
        'current_balance',
        'is_active',
        'is_default_payment',
        'exclude_from_total',
        'color',
    ];

    protected $appends = ['balance', 'amount_owed', 'utilization_rate'];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'float',
            'current_balance' => 'float',
            'is_active' => 'boolean',
            'is_default_payment' => 'boolean',
            'exclude_from_total' => 'boolean',
            'has_credit_limit' => 'boolean',
            'account_number' => 'encrypted',
        ];
    }

    /**
     * Set this account as default payment, clearing others
     */
    public function setAsDefaultPayment(): void
    {
        // Clear other defaults for this user
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default_payment' => false]);

        $this->is_default_payment = true;
        $this->save();
    }

    /**
     * Get the default payment account for a user
     */
    public static function getDefaultPayment(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('is_default_payment', true)
            ->where('is_active', true)
            ->first();
    }

    public function getBalanceAttribute(): float
    {
        return (float) ($this->current_balance ?? 0);
    }

    /**
     * Get amount owed for accounts with credit limit
     * For credit accounts: initial_balance (limit) - current_balance (available) = amount spent/owed
     */
    public function getAmountOwedAttribute(): float
    {
        if (! $this->has_credit_limit) {
            return 0;
        }

        $owed = ($this->initial_balance ?? 0) - ($this->current_balance ?? 0);

        return max(0, $owed);
    }

    /**
     * Get utilization rate for accounts with credit limit (percentage)
     */
    public function getUtilizationRateAttribute(): float
    {
        if (! $this->has_credit_limit) {
            return 0;
        }

        $initialBalance = $this->initial_balance ?? 0;
        if ($initialBalance <= 0) {
            return 0;
        }

        return round(($this->amount_owed / $initialBalance) * 100, 1);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getBalanceMoney(): Money
    {
        return new Money($this->current_balance ?? 0, $this->currency_code);
    }

    public function updateBalance(float $amount): void
    {
        $this->current_balance += $amount;
        $this->save();
    }

    protected static function newFactory()
    {
        return \Modules\Finance\Database\Factories\AccountFactory::new();
    }
}
