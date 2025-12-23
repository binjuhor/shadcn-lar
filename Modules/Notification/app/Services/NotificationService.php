<?php

namespace Modules\Notification\Services;

use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Notifications\GenericNotification;
use Spatie\Permission\Models\Role;

class NotificationService
{
    public function sendToUser(
        User $user,
        string $title,
        string $message,
        NotificationCategory $category,
        array $channels = [NotificationChannel::DATABASE],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $icon = null
    ): void {
        $notification = new GenericNotification(
            title: $title,
            message: $message,
            category: $category,
            channels: $channels,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            icon: $icon
        );

        $user->notify($notification);
    }

    public function sendToUsers(
        Collection|array $users,
        string $title,
        string $message,
        NotificationCategory $category,
        array $channels = [NotificationChannel::DATABASE],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $icon = null
    ): void {
        $users = $users instanceof Collection ? $users : collect($users);

        $notification = new GenericNotification(
            title: $title,
            message: $message,
            category: $category,
            channels: $channels,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            icon: $icon
        );

        Notification::send($users, $notification);
    }

    public function sendToRole(
        string|Role $role,
        string $title,
        string $message,
        NotificationCategory $category,
        array $channels = [NotificationChannel::DATABASE],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $icon = null
    ): void {
        $roleName = $role instanceof Role ? $role->name : $role;
        $users = User::role($roleName)->get();

        $this->sendToUsers(
            users: $users,
            title: $title,
            message: $message,
            category: $category,
            channels: $channels,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            icon: $icon
        );
    }

    public function broadcast(
        string $title,
        string $message,
        NotificationCategory $category,
        array $channels = [NotificationChannel::DATABASE],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $icon = null
    ): void {
        $users = User::all();

        $this->sendToUsers(
            users: $users,
            title: $title,
            message: $message,
            category: $category,
            channels: $channels,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            icon: $icon
        );
    }

    public function sendFromTemplate(
        NotificationTemplate $template,
        User|Collection|array $recipients,
        array $variables = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null
    ): void {
        $notification = GenericNotification::fromTemplate(
            template: $template,
            variables: $variables,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel
        );

        if ($recipients instanceof User) {
            $recipients->notify($notification);
        } else {
            $recipients = $recipients instanceof Collection ? $recipients : collect($recipients);
            Notification::send($recipients, $notification);
        }
    }

    public function sendFromTemplateBySlug(
        string $slug,
        User|Collection|array $recipients,
        array $variables = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null
    ): void {
        $template = NotificationTemplate::where('slug', $slug)
            ->active()
            ->firstOrFail();

        $this->sendFromTemplate(
            template: $template,
            recipients: $recipients,
            variables: $variables,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel
        );
    }
}
