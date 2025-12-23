<?php

namespace Modules\Notification\Enums;

enum NotificationCategory: string
{
    case COMMUNICATION = 'communication';
    case MARKETING = 'marketing';
    case SECURITY = 'security';
    case SYSTEM = 'system';
    case TRANSACTIONAL = 'transactional';

    public function label(): string
    {
        return match ($this) {
            self::COMMUNICATION => 'Communication',
            self::MARKETING => 'Marketing',
            self::SECURITY => 'Security',
            self::SYSTEM => 'System Alerts',
            self::TRANSACTIONAL => 'Transactional',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::COMMUNICATION => 'Messages, mentions, and direct communications',
            self::MARKETING => 'Product updates, promotions, and newsletters',
            self::SECURITY => 'Login alerts, password changes, and security events',
            self::SYSTEM => 'Maintenance notices, updates, and service status',
            self::TRANSACTIONAL => 'Orders, payments, and receipts',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::COMMUNICATION => 'message-circle',
            self::MARKETING => 'megaphone',
            self::SECURITY => 'shield',
            self::SYSTEM => 'server',
            self::TRANSACTIONAL => 'receipt',
        };
    }
}
