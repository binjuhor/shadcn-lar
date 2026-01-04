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
        'exclude_from_total',
        'color',
    ];

    protected $appends = ['balance'];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'integer',
            'current_balance' => 'integer',
            'is_active' => 'boolean',
            'exclude_from_total' => 'boolean',
            'account_number' => 'encrypted',
        ];
    }

    public function getBalanceAttribute(): int
    {
        return $this->current_balance ?? 0;
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

    public function updateBalance(int $amountInCents): void
    {
        $this->current_balance += $amountInCents;
        $this->save();
    }

    protected static function newFactory()
    {
        return \Modules\Finance\Database\Factories\AccountFactory::new();
    }
}
