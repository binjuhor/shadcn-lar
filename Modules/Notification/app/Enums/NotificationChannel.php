<?php

namespace Modules\Notification\Enums;

enum NotificationChannel: string
{
    case DATABASE = 'database';
    case EMAIL = 'email';
    case SMS = 'sms';
    case PUSH = 'push';

    public function label(): string
    {
        return match ($this) {
            self::DATABASE => 'In-App',
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::PUSH => 'Push',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DATABASE => 'Show in notification center',
            self::EMAIL => 'Send to email address',
            self::SMS => 'Text message to phone',
            self::PUSH => 'Browser/mobile push notification',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DATABASE => 'bell',
            self::EMAIL => 'mail',
            self::SMS => 'smartphone',
            self::PUSH => 'bell-ring',
        };
    }

    public function driver(): string
    {
        return match ($this) {
            self::DATABASE => 'database',
            self::EMAIL => 'mail',
            self::SMS => 'vonage',
            self::PUSH => 'fcm',
        };
    }
}
