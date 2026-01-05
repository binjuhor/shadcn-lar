<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Finance\ValueObjects\Money;
use OwenIt\Auditing\Contracts\Auditable;

class SavingsContribution extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table = 'finance_savings_contributions';

    protected $fillable = [
        'savings_goal_id',
        'transaction_id',
        'amount',
        'currency_code',
        'contribution_date',
        'notes',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'contribution_date' => 'date',
        ];
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function getAmountMoney(): Money
    {
        return new Money($this->amount, $this->currency_code);
    }

    public function isLinked(): bool
    {
        return $this->type === 'linked' && $this->transaction_id !== null;
    }

    public function isWithdrawal(): bool
    {
        return $this->amount < 0;
    }

    protected static function newFactory()
    {
        return \Modules\Finance\Database\Factories\SavingsContributionFactory::new();
    }
}
