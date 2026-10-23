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
use Modules\Finance\Database\Factories\BorrowingFactory;
use Modules\Finance\ValueObjects\Money;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Borrowing extends Model implements Auditable, HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table = 'finance_borrowings';

    protected $fillable = [
        'user_id',
        'transaction_id',
        'lender_name',
        'amount',
        'amount_repaid',
        'currency_code',
        'borrowed_date',
        'due_date',
        'message',
        'status',
    ];

    // Eager-load media so the `proofs` accessor doesn't trigger N+1 in list views.
    protected $with = ['media'];

    protected $appends = ['remaining_amount', 'progress_percent', 'proofs'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'amount_repaid' => 'float',
            'borrowed_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Proof images for the borrowing (screenshots, photos of cash/IOU notes).
        // Multiple files allowed. Disk follows the project-wide media-library config (R2).
        $this->addMediaCollection('proofs')
            ->useDisk(config('media-library.disk_name'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(BorrowingRepayment::class);
    }

    /**
     * Flat list of attached proof images for the JSON payload.
     *
     * @return array<int, array{id:int,url:string,name:string,mime_type:?string,size:int}>
     */
    public function getProofsAttribute(): array
    {
        return $this->getMedia('proofs')->map(fn ($m) => [
            'id' => $m->id,
            'url' => $m->getFullUrl(),
            'name' => $m->file_name,
            'mime_type' => $m->mime_type,
            'size' => $m->size,
        ])->all();
    }

    public function getRemainingAmountAttribute(): float
    {
        return max($this->amount - $this->amount_repaid, 0);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->amount == 0) {
            return 0;
        }

        return min(($this->amount_repaid / $this->amount) * 100, 100);
    }

    public function getAmountMoney(): Money
    {
        return new Money($this->amount, $this->currency_code);
    }

    public function getRemainingMoney(): Money
    {
        return new Money($this->remaining_amount, $this->currency_code);
    }

    public function isRepaid(): bool
    {
        return $this->status === 'repaid';
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['active', 'partially_repaid']);
    }

    protected static function newFactory()
    {
        return BorrowingFactory::new();
    }
}
