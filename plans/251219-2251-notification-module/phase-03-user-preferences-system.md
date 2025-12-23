# Phase 3: User Preferences System

## Context

- Priority: High
- Status: Pending
- Dependencies: Phase 1 (Database Schema)

## Overview

Backend system for managing user notification preferences with default values and admin override capability.

## Key Insights

- Default preferences created on user registration
- Preferences stored per category+channel combination
- Admin can set system-wide defaults
- Security notifications have limited user control

## Requirements

### Functional
- CRUD operations for user preferences
- Default preference initialization
- Bulk update preferences
- Admin can view/manage any user's preferences
- Channel availability varies by category

### Non-functional
- Cache preferences for performance
- Validate preferences against allowed combinations

## Architecture

### Default Preferences Matrix

| Category | Database | Email | SMS | Push |
|----------|----------|-------|-----|------|
| Communication | ✓ On | ✓ On | ○ Off | ○ Off |
| Marketing | ✓ On | ○ Off | ○ Off | ○ Off |
| Security | ✓ Always | ✓ Always | ○ Off | ○ Off |
| System | ✓ On | ✓ On | ○ Off | ○ Off |
| Transactional | ✓ On | ✓ On | ○ Off | ○ Off |

## Related Code Files

### Create
| File | Action | Description |
|------|--------|-------------|
| `app/Http/Controllers/NotificationPreferenceController.php` | Create | API controller |
| `app/Http/Requests/UpdateNotificationPreferencesRequest.php` | Create | Validation |
| `app/Services/NotificationPreferenceService.php` | Create | Business logic |
| `config/notifications.php` | Create | Configuration |

### Modify
| File | Action | Description |
|------|--------|-------------|
| `app/Models/User.php` | Modify | Add preference helpers |
| `routes/dashboard.php` | Modify | Add preference routes |

## Implementation Steps

### Step 1: Create Configuration File

```php
// config/notifications.php
return [
    'categories' => [
        'communication' => [
            'label' => 'Communication',
            'description' => 'Messages, mentions, and direct communications',
            'icon' => 'message-circle',
            'channels' => ['database', 'email', 'sms', 'push'],
            'defaults' => [
                'database' => true,
                'email' => true,
                'sms' => false,
                'push' => false,
            ],
            'user_configurable' => true,
        ],
        'marketing' => [
            'label' => 'Marketing',
            'description' => 'Product updates, promotions, and newsletters',
            'icon' => 'megaphone',
            'channels' => ['database', 'email'],
            'defaults' => [
                'database' => true,
                'email' => false,
            ],
            'user_configurable' => true,
        ],
        'security' => [
            'label' => 'Security',
            'description' => 'Login alerts, password changes, and security events',
            'icon' => 'shield',
            'channels' => ['database', 'email', 'sms'],
            'defaults' => [
                'database' => true,
                'email' => true,
                'sms' => false,
            ],
            'user_configurable' => false, // Security always enabled for database/email
            'force_enabled' => ['database', 'email'],
        ],
        'system' => [
            'label' => 'System Alerts',
            'description' => 'Maintenance notices, updates, and service status',
            'icon' => 'server',
            'channels' => ['database', 'email'],
            'defaults' => [
                'database' => true,
                'email' => true,
            ],
            'user_configurable' => true,
        ],
        'transactional' => [
            'label' => 'Transactional',
            'description' => 'Orders, payments, and receipts',
            'icon' => 'receipt',
            'channels' => ['database', 'email', 'sms'],
            'defaults' => [
                'database' => true,
                'email' => true,
                'sms' => false,
            ],
            'user_configurable' => true,
        ],
    ],

    'channels' => [
        'database' => [
            'label' => 'In-App',
            'description' => 'Show in notification center',
            'icon' => 'bell',
            'enabled' => true,
        ],
        'email' => [
            'label' => 'Email',
            'description' => 'Send to email address',
            'icon' => 'mail',
            'enabled' => true,
        ],
        'sms' => [
            'label' => 'SMS',
            'description' => 'Text message to phone',
            'icon' => 'smartphone',
            'enabled' => env('NOTIFICATIONS_SMS_ENABLED', false),
        ],
        'push' => [
            'label' => 'Push',
            'description' => 'Browser/mobile push notification',
            'icon' => 'bell-ring',
            'enabled' => env('NOTIFICATIONS_PUSH_ENABLED', false),
        ],
    ],
];
```

### Step 2: Create Preference Service

```php
// app/Services/NotificationPreferenceService.php
namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificationPreferenceService
{
    public function initializeDefaultsForUser(User $user): void
    {
        $categories = config('notifications.categories');
        $preferences = [];

        foreach ($categories as $categoryKey => $categoryConfig) {
            foreach ($categoryConfig['defaults'] as $channelKey => $enabled) {
                $preferences[] = [
                    'user_id' => $user->id,
                    'category' => $categoryKey,
                    'channel' => $channelKey,
                    'enabled' => $enabled,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        NotificationPreference::insert($preferences);
        $this->clearCache($user);
    }

    public function getPreferencesForUser(User $user): Collection
    {
        return Cache::remember(
            "notification_preferences_{$user->id}",
            now()->addHours(1),
            fn() => $user->notificationPreferences()->get()
        );
    }

    public function getGroupedPreferences(User $user): array
    {
        $preferences = $this->getPreferencesForUser($user);
        $categories = config('notifications.categories');
        $result = [];

        foreach ($categories as $categoryKey => $categoryConfig) {
            $result[$categoryKey] = [
                'label' => $categoryConfig['label'],
                'description' => $categoryConfig['description'],
                'icon' => $categoryConfig['icon'],
                'user_configurable' => $categoryConfig['user_configurable'],
                'force_enabled' => $categoryConfig['force_enabled'] ?? [],
                'channels' => [],
            ];

            foreach ($categoryConfig['channels'] as $channelKey) {
                $preference = $preferences
                    ->where('category', $categoryKey)
                    ->where('channel', $channelKey)
                    ->first();

                $isForced = in_array($channelKey, $categoryConfig['force_enabled'] ?? []);

                $result[$categoryKey]['channels'][$channelKey] = [
                    'enabled' => $preference?->enabled ?? $categoryConfig['defaults'][$channelKey],
                    'configurable' => $categoryConfig['user_configurable'] && !$isForced,
                    'forced' => $isForced,
                ];
            }
        }

        return $result;
    }

    public function updatePreference(
        User $user,
        NotificationCategory $category,
        NotificationChannel $channel,
        bool $enabled
    ): NotificationPreference {
        // Check if category allows user configuration
        $categoryConfig = config("notifications.categories.{$category->value}");

        if (!$categoryConfig['user_configurable']) {
            throw new \InvalidArgumentException("Category {$category->value} is not user configurable");
        }

        $forcedChannels = $categoryConfig['force_enabled'] ?? [];
        if (in_array($channel->value, $forcedChannels)) {
            throw new \InvalidArgumentException("Channel {$channel->value} cannot be disabled for {$category->value}");
        }

        $preference = NotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'category' => $category,
                'channel' => $channel,
            ],
            ['enabled' => $enabled]
        );

        $this->clearCache($user);

        return $preference;
    }

    public function bulkUpdatePreferences(User $user, array $preferences): void
    {
        foreach ($preferences as $pref) {
            try {
                $this->updatePreference(
                    $user,
                    NotificationCategory::from($pref['category']),
                    NotificationChannel::from($pref['channel']),
                    $pref['enabled']
                );
            } catch (\InvalidArgumentException $e) {
                // Skip non-configurable preferences
                continue;
            }
        }
    }

    protected function clearCache(User $user): void
    {
        Cache::forget("notification_preferences_{$user->id}");
    }
}
```

### Step 3: Create Controller

```php
// app/Http/Controllers/NotificationPreferenceController.php
namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        protected NotificationPreferenceService $preferenceService
    ) {}

    public function index(Request $request)
    {
        $preferences = $this->preferenceService->getGroupedPreferences(
            $request->user()
        );

        return Inertia::render('settings/notifications/index', [
            'preferences' => $preferences,
            'channels' => config('notifications.channels'),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request)
    {
        $this->preferenceService->bulkUpdatePreferences(
            $request->user(),
            $request->validated('preferences')
        );

        return back()->with('success', 'Notification preferences updated');
    }
}
```

### Step 4: Create Request Validation

```php
// app/Http/Requests/UpdateNotificationPreferencesRequest.php
namespace App\Http\Requests;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.*.category' => ['required', Rule::enum(NotificationCategory::class)],
            'preferences.*.channel' => ['required', Rule::enum(NotificationChannel::class)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
```

### Step 5: Add Routes

```php
// routes/dashboard.php (add to settings group)
Route::prefix('settings/notifications')->group(function () {
    Route::get('/', [NotificationPreferenceController::class, 'index'])
        ->name('settings.notifications');
    Route::put('/preferences', [NotificationPreferenceController::class, 'update'])
        ->name('settings.notifications.update');
});
```

### Step 6: Initialize on User Registration

```php
// app/Listeners/InitializeUserNotificationPreferences.php
namespace App\Listeners;

use App\Services\NotificationPreferenceService;
use Illuminate\Auth\Events\Registered;

class InitializeUserNotificationPreferences
{
    public function __construct(
        protected NotificationPreferenceService $preferenceService
    ) {}

    public function handle(Registered $event): void
    {
        $this->preferenceService->initializeDefaultsForUser($event->user);
    }
}

// Register in EventServiceProvider
protected $listen = [
    Registered::class => [
        InitializeUserNotificationPreferences::class,
    ],
];
```

## Todo List

- [ ] Create config/notifications.php
- [ ] Create NotificationPreferenceService
- [ ] Create NotificationPreferenceController
- [ ] Create UpdateNotificationPreferencesRequest
- [ ] Add routes to dashboard.php
- [ ] Create InitializeUserNotificationPreferences listener
- [ ] Register listener in EventServiceProvider
- [ ] Test preference CRUD operations
- [ ] Test caching behavior

## Success Criteria

- [ ] Default preferences created for new users
- [ ] Users can update their preferences
- [ ] Security preferences cannot be disabled
- [ ] Preferences are cached
- [ ] Bulk updates work correctly
- [ ] API returns grouped preferences

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Cache stale data | Low | Clear cache on update |
| Missing default prefs | Medium | Create migration to initialize existing users |

## Security Considerations

- Validate category/channel combinations exist
- Users can only update their own preferences
- Admin permission required for other users' preferences
- Force-enabled channels cannot be bypassed

## Next Steps

→ Phase 4: Notification Center UI
