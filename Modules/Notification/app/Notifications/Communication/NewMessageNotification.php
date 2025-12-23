<?php

namespace Modules\Notification\Notifications\Communication;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Notifications\BaseNotification;

class NewMessageNotification extends BaseNotification
{
    public function __construct(
        protected User $sender,
        protected string $messagePreview,
        protected int $conversationId
    ) {}

    public function getCategory(): NotificationCategory
    {
        return NotificationCategory::COMMUNICATION;
    }

    protected function getTitle(): string
    {
        return "New message from {$this->sender->name}";
    }

    protected function getMessage(): string
    {
        return $this->messagePreview;
    }

    protected function getIcon(): string
    {
        return 'message-circle';
    }

    protected function getActionUrl(): string
    {
        return url("/dashboard/chats?conversation={$this->conversationId}");
    }

    protected function getActionText(): string
    {
        return 'View Message';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New message from {$this->sender->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->sender->name} sent you a message:")
            ->line("\"{$this->messagePreview}\"")
            ->action($this->getActionText(), $this->getActionUrl());
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'conversation_id' => $this->conversationId,
        ]);
    }
}
