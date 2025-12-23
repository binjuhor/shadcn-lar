<?php

namespace Modules\Notification\Notifications\System;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Notifications\BaseNotification;

class MaintenanceNotification extends BaseNotification
{
    public function __construct(
        protected string $title,
        protected string $description,
        protected ?string $scheduledAt = null,
        protected ?string $estimatedDuration = null
    ) {}

    public function getCategory(): NotificationCategory
    {
        return NotificationCategory::SYSTEM;
    }

    protected function getTitle(): string
    {
        return $this->title;
    }

    protected function getMessage(): string
    {
        $message = $this->description;

        if ($this->scheduledAt) {
            $message .= " Scheduled for: {$this->scheduledAt}.";
        }

        if ($this->estimatedDuration) {
            $message .= " Estimated duration: {$this->estimatedDuration}.";
        }

        return $message;
    }

    protected function getIcon(): string
    {
        return 'tool';
    }

    protected function getActionUrl(): ?string
    {
        return url('/dashboard');
    }

    protected function getActionText(): ?string
    {
        return 'Go to Dashboard';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->description);

        if ($this->scheduledAt) {
            $mail->line("**Scheduled for:** {$this->scheduledAt}");
        }

        if ($this->estimatedDuration) {
            $mail->line("**Estimated duration:** {$this->estimatedDuration}");
        }

        $mail->line('We apologize for any inconvenience this may cause.');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'scheduled_at' => $this->scheduledAt,
            'estimated_duration' => $this->estimatedDuration,
        ]);
    }
}
