<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    Relations\BelongsTo,
    SoftDeletes
};
use Modules\Finance\ValueObjects\Money;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Transaction extends Model implements Auditable, HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
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

    // Eager-load media so the `bills` accessor doesn't trigger N+1 in list views.
    protected $with = ['media'];

    protected $appends = ['type', 'bills'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'transaction_date' => 'date',
            'reconciled_at' => 'datetime',
        ];
    }

    public function getTypeAttribute(): string
    {
        return $this->transaction_type;
    }

    /**
     * Flat list of attached bill/receipt files for the JSON payload.
     * Each entry exposes only the fields the UI needs (id, url, name, mime, size).
     *
     * @return array<int, array{id:int,url:string,name:string,mime_type:?string,size:int}>
     */
    public function getBillsAttribute(): array
    {
        return $this->getMedia('bills')->map(fn ($m) => [
            'id' => $m->id,
            'url' => $m->getFullUrl(),
            'name' => $m->file_name,
            'mime_type' => $m->mime_type,
            'size' => $m->size,
        ])->all();
    }

    public function registerMediaCollections(): void
    {
        // Stores receipts/bills/screenshots attached to a transaction.
        // Multiple files allowed. Disk follows the project-wide media-library config (R2).
        $this->addMediaCollection('bills')
            ->useDisk(config('media-library.disk_name'));
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
