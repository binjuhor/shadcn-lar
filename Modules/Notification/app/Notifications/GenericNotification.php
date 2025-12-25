<?php

namespace Modules\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;

class GenericNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $title;

    protected string $message;

    protected NotificationCategory $category;

    protected array $channels;

    protected ?string $actionUrl;

    protected ?string $actionLabel;

    protected ?string $icon;

    protected array $data;

    public function __construct(
        string $title,
        string $message,
        NotificationCategory $category,
        array $channels = [NotificationChannel::DATABASE],
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $icon = null,
        array $data = []
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->category = $category;
        $this->channels = $channels;
        $this->actionUrl = $actionUrl;
        $this->actionLabel = $actionLabel;
        $this->icon = $icon ?? $category->icon();
        $this->data = $data;
    }

    public static function fromTemplate(
        NotificationTemplate $template,
        array $variables = [],
        ?string $actionUrl = null,
        ?string $actionLabel = null
    ): self {
        $rendered = $template->render($variables);
        $channels = array_map(
            fn ($c) => NotificationChannel::from($c),
            $template->channels
        );

        return new self(
            title: $rendered['subject'],
            message: $rendered['body'],
            category: $template->category,
            channels: $channels,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            data: $variables
        );
    }

    public function via(object $notifiable): array
    {
        return array_map(
            fn ($channel) => $channel instanceof NotificationChannel ? $channel->driver() : $channel,
            $this->channels
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->message);

        if ($this->actionUrl && $this->actionLabel) {
            $mail->action($this->actionLabel, $this->actionUrl);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'category' => $this->category->value,
            'icon' => $this->icon,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'data' => $this->data,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
