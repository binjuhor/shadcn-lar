<?php

namespace Modules\Notification\Notifications\Marketing;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Notifications\BaseNotification;

class NewFeatureNotification extends BaseNotification
{
    public function __construct(
        protected string $featureName,
        protected string $description,
        protected ?string $learnMoreUrl = null
    ) {}

    public function getCategory(): NotificationCategory
    {
        return NotificationCategory::MARKETING;
    }

    protected function getTitle(): string
    {
        return "New Feature: {$this->featureName}";
    }

    protected function getMessage(): string
    {
        return $this->description;
    }

    protected function getIcon(): string
    {
        return 'sparkles';
    }

    protected function getActionUrl(): ?string
    {
        return $this->learnMoreUrl;
    }

    protected function getActionText(): ?string
    {
        return $this->learnMoreUrl ? 'Learn More' : null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Introducing: {$this->featureName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("We're excited to announce a new feature!")
            ->line("**{$this->featureName}**")
            ->line($this->description);

        if ($this->learnMoreUrl) {
            $mail->action('Learn More', $this->learnMoreUrl);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'feature_name' => $this->featureName,
        ]);
    }
}
