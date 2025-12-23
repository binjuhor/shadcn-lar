<?php

namespace Modules\Notification\Policies;

use App\Models\User;
use Modules\Notification\Models\Notification;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notification $notification): bool
    {
        return $notification->notifiable_type === User::class
            && $notification->notifiable_id === $user->id;
    }

    public function update(User $user, Notification $notification): bool
    {
        return $notification->notifiable_type === User::class
            && $notification->notifiable_id === $user->id;
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $notification->notifiable_type === User::class
            && $notification->notifiable_id === $user->id;
    }

    public function markAsRead(User $user, Notification $notification): bool
    {
        return $this->update($user, $notification);
    }

    public function markAllAsRead(User $user): bool
    {
        return true;
    }
}
