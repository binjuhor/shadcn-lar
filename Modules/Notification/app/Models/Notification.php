<?php

namespace Modules\Notification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notifiable_id')
            ->where('notifiable_type', User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('data->category', $category);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId);
    }

    public function getCategoryAttribute(): ?string
    {
        return $this->data['category'] ?? null;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->data['title'] ?? null;
    }

    public function getMessageAttribute(): ?string
    {
        return $this->data['message'] ?? null;
    }

    public function getIconAttribute(): ?string
    {
        return $this->data['icon'] ?? null;
    }

    public function getActionUrlAttribute(): ?string
    {
        return $this->data['action_url'] ?? null;
    }

    public function getActionLabelAttribute(): ?string
    {
        return $this->data['action_label'] ?? null;
    }
}
