<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Finance\ValueObjects\Money;
use OwenIt\Auditing\Contracts\Auditable;

class Transaction extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table = 'finance_transactions';

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'transaction_type',
        'amount',
        'currency_code',
        'description',
        'notes',
        'transaction_date',
        'reconciled_at',
        'transfer_account_id',
        'transfer_transaction_id',
    ];

    protected $appends = ['type'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'reconciled_at' => 'datetime',
        ];
    }

    public function getTypeAttribute(): string
    {
        return $this->transaction_type;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function transferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_account_id');
    }

    public function transferTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transfer_transaction_id');
    }

    public function isTransfer(): bool
    {
        return $this->transfer_transaction_id !== null;
    }

    public function getAmountMoney(): Money
    {
        return new Money($this->amount, $this->currency_code);
    }

    public function isReconciled(): bool
    {
        return ! is_null($this->reconciled_at);
    }

    protected static function newFactory()
    {
        return \Modules\Finance\Database\Factories\TransactionFactory::new();
    }
}
