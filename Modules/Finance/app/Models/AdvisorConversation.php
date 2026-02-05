<?php

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\{
    Builder,
    Model,
    Relations\BelongsTo,
    Relations\HasMany,
    Relations\HasOne,
    SoftDeletes
};

class AdvisorConversation extends Model
{
    use SoftDeletes;

    protected $table = 'finance_advisor_conversations';

    protected $fillable = [
        'user_id',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AdvisorMessage::class, 'conversation_id')
            ->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(AdvisorMessage::class, 'conversation_id')
            ->latestOfMany();
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
