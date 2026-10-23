<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    Relations\BelongsTo,
    SoftDeletes
};
use OwenIt\Auditing\Contracts\Auditable;

class BorrowingRepayment extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table = 'finance_borrowing_repayments';

    protected $fillable = [
        'borrowing_id',
        'amount',
        'transaction_id',
        'repaid_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'repaid_at' => 'date',
        ];
    }

    public function borrowing(): BelongsTo
    {
        return $this->belongsTo(Borrowing::class);
    }
}
