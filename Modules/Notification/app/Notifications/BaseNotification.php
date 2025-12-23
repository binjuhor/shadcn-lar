<?php

namespace Modules\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function getCategory(): NotificationCategory;

    public function via(object $notifiable): array
    {
        $channels = [];
        $availableChannels = $this->getAvailableChannels();

        foreach ($availableChannels as $channel) {
            if ($this->shouldSendToChannel($notifiable, $channel)) {
                $channels[] = $this->mapChannelToDriver($channel);
            }
        }

        return $channels;
    }

    protected function getAvailableChannels(): array
    {
        return [
            NotificationChannel::DATABASE,
            NotificationChannel::EMAIL,
        ];
    }

    protected function shouldSendToChannel(object $notifiable, NotificationChannel $channel): bool
    {
        // Security notifications always send (non-dismissable)
        if ($this->getCategory() === NotificationCategory::SECURITY) {
            return true;
        }

        return $notifiable->canReceiveNotification(
            $this->getCategory(),
            $channel
        );
    }

    protected function mapChannelToDriver(NotificationChannel $channel): string
    {
        return $channel->driver();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->getCategory()->value,
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'action_url' => $this->getActionUrl(),
            'action_text' => $this->getActionText(),
            'icon' => $this->getIcon(),
        ];
    }

    abstract protected function getTitle(): string;

    abstract protected function getMessage(): string;

    protected function getActionUrl(): ?string
    {
        return null;
    }

    protected function getActionText(): ?string
    {
        return null;
    }

    protected function getIcon(): ?string
    {
        return null;
    }
}
