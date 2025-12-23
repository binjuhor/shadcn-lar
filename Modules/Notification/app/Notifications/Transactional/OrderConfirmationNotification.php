<?php

namespace Modules\Notification\Notifications\Transactional;

use Illuminate\Notifications\Messages\MailMessage;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Notifications\BaseNotification;

class OrderConfirmationNotification extends BaseNotification
{
    public function __construct(
        protected string $orderId,
        protected string $orderTotal,
        protected int $itemCount,
        protected ?string $orderUrl = null
    ) {}

    public function getCategory(): NotificationCategory
    {
        return NotificationCategory::TRANSACTIONAL;
    }

    protected function getTitle(): string
    {
        return "Order #{$this->orderId} Confirmed";
    }

    protected function getMessage(): string
    {
        return "Your order with {$this->itemCount} item(s) totaling {$this->orderTotal} has been confirmed.";
    }

    protected function getIcon(): string
    {
        return 'shopping-bag';
    }

    protected function getActionUrl(): ?string
    {
        return $this->orderUrl ?? url("/dashboard/orders/{$this->orderId}");
    }

    protected function getActionText(): string
    {
        return 'View Order';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$this->orderId} Confirmed")
            ->greeting("Hello {$notifiable->name},")
            ->line('Thank you for your order!')
            ->line("**Order ID:** #{$this->orderId}")
            ->line("**Items:** {$this->itemCount}")
            ->line("**Total:** {$this->orderTotal}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('We will notify you when your order ships.');
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'order_id' => $this->orderId,
            'order_total' => $this->orderTotal,
            'item_count' => $this->itemCount,
        ]);
    }
}
