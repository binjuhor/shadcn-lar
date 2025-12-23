<?php

namespace Modules\Notification\Notifications\Security;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Notifications\BaseNotification;

class LoginAlertNotification extends BaseNotification
{
    public function __construct(
        protected string $ipAddress,
        protected string $userAgent,
        protected string $location = 'Unknown'
    ) {}

    public function getCategory(): NotificationCategory
    {
        return NotificationCategory::SECURITY;
    }

    protected function getTitle(): string
    {
        return 'New Login Detected';
    }

    protected function getMessage(): string
    {
        return "A new login to your account was detected from {$this->location} ({$this->ipAddress}).";
    }

    protected function getIcon(): string
    {
        return 'shield-alert';
    }

    protected function getActionUrl(): string
    {
        return url('/dashboard/settings/account');
    }

    protected function getActionText(): string
    {
        return 'Review Security Settings';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Login to Your Account')
            ->greeting("Hello {$notifiable->name},")
            ->line($this->getMessage())
            ->line("Device: {$this->userAgent}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('If this was not you, please secure your account immediately.');
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'location' => $this->location,
        ]);
    }
}
