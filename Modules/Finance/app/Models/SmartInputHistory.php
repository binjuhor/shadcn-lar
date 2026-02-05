<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SmartInputHistory extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'finance_smart_input_histories';

    protected $fillable = [
        'user_id',
        'transaction_id',
        'input_type',
        'raw_text',
        'parsed_result',
        'ai_provider',
        'language',
        'confidence',
        'transaction_saved',
    ];

    protected function casts(): array
    {
        return [
            'parsed_result' => 'array',
            'confidence' => 'float',
            'transaction_saved' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('input_attachments')
            ->useDisk('public');
    }
}
