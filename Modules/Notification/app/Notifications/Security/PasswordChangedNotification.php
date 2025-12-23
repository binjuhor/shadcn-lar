<?php

namespace Modules\Notification\Notifications\Security;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Notifications\BaseNotification;

class PasswordChangedNotification extends BaseNotification
{
    public function __construct(
        protected string $ipAddress,
        protected string $changedAt
    ) {}

    public function getCategory(): NotificationCategory
    {
        return NotificationCategory::SECURITY;
    }

    protected function getTitle(): string
    {
        return 'Password Changed';
    }

    protected function getMessage(): string
    {
        return "Your password was changed on {$this->changedAt}.";
    }

    protected function getIcon(): string
    {
        return 'key';
    }

    protected function getActionUrl(): string
    {
        return url('/dashboard/settings/account');
    }

    protected function getActionText(): string
    {
        return 'Review Account Settings';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Password Has Been Changed')
            ->greeting("Hello {$notifiable->name},")
            ->line($this->getMessage())
            ->line("IP Address: {$this->ipAddress}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('If you did not make this change, please reset your password immediately.');
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'ip_address' => $this->ipAddress,
            'changed_at' => $this->changedAt,
        ]);
    }
}
