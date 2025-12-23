<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'bio',
        'urls',
        'language',
        'dob',
        'appearance_settings',
        'notification_settings',
        'display_settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
            'urls' => 'array',
            'appearance_settings' => 'array',
            'notification_settings' => 'array',
            'display_settings' => 'array',
        ];
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function canReceiveNotification(
        NotificationCategory $category,
        NotificationChannel $channel
    ): bool {
        $preference = $this->notificationPreferences()
            ->where('category', $category)
            ->where('channel', $channel)
            ->first();

        return $preference?->enabled ?? true;
    }
}
