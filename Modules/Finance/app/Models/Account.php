<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    protected $appends = ['balance'];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_default_payment' => 'boolean',
            'exclude_from_total' => 'boolean',
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
