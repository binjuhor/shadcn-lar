<?php

namespace Modules\Notification\Policies;

use App\Models\User;
use Modules\Notification\Models\NotificationTemplate;

class NotificationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notifications.templates.view');
    }

    public function view(User $user, NotificationTemplate $template): bool
    {
        return $user->can('notifications.templates.view');
    }

    public function create(User $user): bool
    {
        return $user->can('notifications.templates.create');
    }

    public function update(User $user, NotificationTemplate $template): bool
    {
        return $user->can('notifications.templates.edit');
    }

    public function delete(User $user, NotificationTemplate $template): bool
    {
        return $user->can('notifications.templates.delete');
    }

    public function send(User $user): bool
    {
        return $user->can('notifications.send');
    }
}
