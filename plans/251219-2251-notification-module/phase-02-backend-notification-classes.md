# Phase 2: Backend Notification Classes

## Context

- Priority: High
- Status: **Completed**
- Dependencies: Phase 1 (Database Schema)

## Overview

Create base notification class and specific notification types for each category with multi-channel support.

## Key Insights

- Laravel Notifications support `via()` method for channel routing
- Each notification class defines its own channels and message formatting
- User preferences filter channels at send time
- Queueable notifications for async delivery

## Requirements

### Functional
- Base notification class with category property
- Specific classes per notification type
- Multi-channel output (database, email, SMS, push)
- Respect user preferences when sending
- Support localization

### Non-functional
- Queueable for performance
- Consistent formatting across channels
- Easy to add new notification types

## Architecture

### Notification Class Hierarchy

```
BaseNotification (abstract)
├── category property
├── respectsPreferences()
├── via() - filters by user preferences
│
├── Communication/
│   ├── NewMessageNotification
│   ├── MentionNotification
│   └── DirectMessageNotification
│
├── Marketing/
│   ├── NewFeatureNotification
│   ├── PromotionNotification
│   └── NewsletterNotification
│
├── Security/
│   ├── LoginAlertNotification
│   ├── PasswordChangedNotification
│   ├── TwoFactorEnabledNotification
│   └── SuspiciousActivityNotification
│
├── System/
│   ├── MaintenanceNotification
│   ├── SystemUpdateNotification
│   └── ErrorAlertNotification
│
├── Transactional/
│   ├── OrderConfirmationNotification
│   ├── PaymentReceivedNotification
│   ├── InvoiceNotification
│   └── ShippingUpdateNotification
```

## Related Code Files

### Create
| File | Action | Description |
|------|--------|-------------|
| `app/Notifications/BaseNotification.php` | Create | Abstract base class |
| `app/Notifications/Communication/NewMessageNotification.php` | Create | New message notification |
| `app/Notifications/Marketing/NewFeatureNotification.php` | Create | Marketing notification |
| `app/Notifications/Security/LoginAlertNotification.php` | Create | Security login alert |
| `app/Notifications/Security/PasswordChangedNotification.php` | Create | Password change alert |
| `app/Notifications/System/MaintenanceNotification.php` | Create | System maintenance |
| `app/Notifications/Transactional/OrderConfirmationNotification.php` | Create | Order confirmation |
| `app/Services/NotificationService.php` | Create | Service for sending |

## Implementation Steps

### Step 1: Create Base Notification Class

```php
// app/Notifications/BaseNotification.php
namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

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
        return match ($channel) {
            NotificationChannel::DATABASE => 'database',
            NotificationChannel::EMAIL => 'mail',
            NotificationChannel::SMS => 'vonage', // or 'twilio'
            NotificationChannel::PUSH => 'fcm',
        };
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
    protected function getActionUrl(): ?string { return null; }
    protected function getActionText(): ?string { return null; }
    protected function getIcon(): ?string { return null; }
}
```

### Step 2: Create Security Notification (Example)

```php
// app/Notifications/Security/LoginAlertNotification.php
namespace App\Notifications\Security;

use App\Enums\NotificationCategory;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

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
        return route('settings.security');
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
```

### Step 3: Create Communication Notification

```php
// app/Notifications/Communication/NewMessageNotification.php
namespace App\Notifications\Communication;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

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
        return route('chats', ['conversation' => $this->conversationId]);
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
}
```

### Step 4: Create Marketing Notification

```php
// app/Notifications/Marketing/NewFeatureNotification.php
namespace App\Notifications\Marketing;

use App\Enums\NotificationCategory;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

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
}
```

### Step 5: Create Notification Service

```php
// app/Services/NotificationService.php
namespace App\Services;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function send(User|array $users, BaseNotification $notification): void
    {
        Notification::send($users, $notification);
    }

    public function sendToAll(BaseNotification $notification): void
    {
        User::chunk(100, function ($users) use ($notification) {
            Notification::send($users, $notification);
        });
    }

    public function sendToUsersWithPreference(
        BaseNotification $notification,
        NotificationCategory $category
    ): void {
        User::whereDoesntHave('notificationPreferences', function ($query) use ($category) {
            $query->where('category', $category)
                  ->where('channel', 'database')
                  ->where('enabled', false);
        })->chunk(100, function ($users) use ($notification) {
            Notification::send($users, $notification);
        });
    }
}
```

## Todo List

- [ ] Create BaseNotification abstract class
- [ ] Create LoginAlertNotification
- [ ] Create PasswordChangedNotification
- [ ] Create NewMessageNotification
- [ ] Create NewFeatureNotification
- [ ] Create MaintenanceNotification
- [ ] Create OrderConfirmationNotification
- [ ] Create NotificationService
- [ ] Register service in AppServiceProvider
- [ ] Test notification delivery

## Success Criteria

- [ ] Base class enforces category requirement
- [ ] Notifications respect user preferences
- [ ] Email templates render correctly
- [ ] Database notifications store proper JSON
- [ ] Security notifications always deliver
- [ ] Notifications are queueable

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Queue failures | High | Use failed_jobs table, retry mechanism |
| Email delivery issues | Medium | Use reliable SMTP, monitor bounces |
| SMS costs | Medium | Rate limit SMS, require opt-in |

## Security Considerations

- Don't include sensitive data in email subjects
- Sanitize user input in notification messages
- Verify user can access linked resources
- Rate limit notifications per user

## Next Steps

→ Phase 3: User Preferences System
