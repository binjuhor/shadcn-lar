<?php

namespace Modules\Notification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Notification\Database\Factories\NotificationPreferenceFactory;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'channel',
        'enabled',
    ];

    protected $casts = [
        'category' => NotificationCategory::class,
        'channel' => NotificationChannel::class,
        'enabled' => 'boolean',
    ];

    protected static function newFactory(): NotificationPreferenceFactory
    {
        return NotificationPreferenceFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
