# Phase 1: Database Schema & Models

## Context

- Priority: High
- Status: **Completed**
- Dependencies: None

## Overview

Create database tables and Eloquent models for storing notifications and user preferences.

## Key Insights

- Laravel's built-in `notifications` table stores in-app notifications
- User preferences stored separately for flexibility
- Polymorphic design allows notifications for any model (not just users)

## Requirements

### Functional
- Store notifications with type, data, read status, timestamps
- Store user notification preferences per category and channel
- Support soft deletes for notification history
- Track notification delivery status per channel

### Non-functional
- Indexed queries for unread count (performance)
- Efficient pagination for notification list
- Minimize storage for high-volume notifications

## Architecture

### Tables

```
notifications (Laravel default)
├── id (uuid)
├── type (string) - notification class name
├── notifiable_type (string) - polymorphic
├── notifiable_id (uuid)
├── data (json) - notification payload
├── read_at (timestamp|null)
├── created_at
├── updated_at

notification_preferences
├── id (bigint)
├── user_id (fk users)
├── category (enum) - communication|marketing|security|system|transactional
├── channel (enum) - database|email|sms|push
├── enabled (boolean)
├── created_at
├── updated_at
├── UNIQUE(user_id, category, channel)

notification_deliveries (optional - for tracking)
├── id (bigint)
├── notification_id (fk notifications)
├── channel (enum)
├── status (enum) - pending|sent|failed|delivered
├── sent_at (timestamp|null)
├── delivered_at (timestamp|null)
├── error_message (text|null)
├── created_at
```

## Related Code Files

### Create
| File | Action | Description |
|------|--------|-------------|
| `database/migrations/XXXX_create_notifications_table.php` | Create | Laravel notifications table |
| `database/migrations/XXXX_create_notification_preferences_table.php` | Create | User preferences table |
| `app/Models/Notification.php` | Create | Notification model |
| `app/Models/NotificationPreference.php` | Create | Preferences model |
| `app/Enums/NotificationCategory.php` | Create | Category enum |
| `app/Enums/NotificationChannel.php` | Create | Channel enum |

### Modify
| File | Action | Description |
|------|--------|-------------|
| `app/Models/User.php` | Modify | Add preferences relationship |

## Implementation Steps

### Step 1: Create Enums
```php
// app/Enums/NotificationCategory.php
enum NotificationCategory: string
{
    case COMMUNICATION = 'communication';
    case MARKETING = 'marketing';
    case SECURITY = 'security';
    case SYSTEM = 'system';
    case TRANSACTIONAL = 'transactional';
}

// app/Enums/NotificationChannel.php
enum NotificationChannel: string
{
    case DATABASE = 'database';
    case EMAIL = 'email';
    case SMS = 'sms';
    case PUSH = 'push';
}
```

### Step 2: Run Laravel Command
```bash
php artisan notifications:table
php artisan migrate
```

### Step 3: Create Preferences Migration
```php
Schema::create('notification_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('category');
    $table->string('channel');
    $table->boolean('enabled')->default(true);
    $table->timestamps();

    $table->unique(['user_id', 'category', 'channel']);
    $table->index(['user_id', 'category']);
});
```

### Step 4: Create Models

**NotificationPreference.php**
```php
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'category', 'channel', 'enabled'];

    protected $casts = [
        'category' => NotificationCategory::class,
        'channel' => NotificationChannel::class,
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Step 5: Update User Model
```php
// Add to User.php
public function notificationPreferences(): HasMany
{
    return $this->hasMany(NotificationPreference::class);
}

public function canReceiveNotification(
    NotificationCategory $category,
    NotificationChannel $channel
): bool {
    $preference = $this->notificationPreferences()
        ->where('category', $category)
        ->where('channel', $channel)
        ->first();

    // Default to enabled if no preference set
    return $preference?->enabled ?? true;
}
```

## Todo List

- [ ] Create NotificationCategory enum
- [ ] Create NotificationChannel enum
- [ ] Run `php artisan notifications:table`
- [ ] Create notification_preferences migration
- [ ] Create NotificationPreference model
- [ ] Update User model with relationship
- [ ] Run migrations
- [ ] Add factory for testing

## Success Criteria

- [ ] Migrations run without errors
- [ ] Models have proper relationships
- [ ] Enums work with model casts
- [ ] User can check notification permissions
- [ ] Unique constraint prevents duplicate preferences

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| UUID vs bigint for notification_id | Medium | Use Laravel default (UUID) for consistency |
| High volume notifications | Medium | Add indexes, consider archiving old notifications |

## Security Considerations

- Cascade delete preferences when user deleted
- Validate enum values in migration/model
- No sensitive data in notification `data` column (use references)

## Next Steps

→ Phase 2: Backend Notification Classes
