# Phase 7: Testing & Validation

## Context

- Priority: Medium
- Status: Pending
- Dependencies: All previous phases

## Overview

Comprehensive testing suite for notification module including unit tests, feature tests, and frontend tests.

## Key Insights

- Laravel's built-in testing for notifications
- Notification::fake() for testing without delivery
- Frontend testing with React Testing Library
- End-to-end validation with browser tests

## Requirements

### Functional
- Unit tests for services and models
- Feature tests for API endpoints
- Notification delivery tests
- User preference tests
- Frontend component tests

### Non-functional
- 80%+ code coverage for notification module
- Fast test execution (< 30s)
- Isolated tests (no side effects)

## Test Coverage Plan

### Backend Tests

| Area | Test File | Coverage |
|------|-----------|----------|
| Models | `NotificationPreferenceTest.php` | Model relationships, scopes |
| Services | `NotificationPreferenceServiceTest.php` | Preference CRUD |
| Services | `NotificationServiceTest.php` | Notification sending |
| API | `NotificationApiTest.php` | All API endpoints |
| Notifications | `SecurityNotificationTest.php` | Security notifications |
| Notifications | `CommunicationNotificationTest.php` | Communication notifications |

### Frontend Tests

| Component | Test File | Coverage |
|-----------|-----------|----------|
| NotificationBell | `notification-bell.test.tsx` | Unread count, dropdown |
| NotificationItem | `notification-item.test.tsx` | Display, actions |
| NotificationsPage | `notifications-page.test.tsx` | List, filtering |
| NotificationsForm | `notifications-form.test.tsx` | Preferences UI |

## Related Code Files

### Create
| File | Action | Description |
|------|--------|-------------|
| `tests/Unit/Models/NotificationPreferenceTest.php` | Create | Model tests |
| `tests/Unit/Services/NotificationPreferenceServiceTest.php` | Create | Service tests |
| `tests/Unit/Services/NotificationServiceTest.php` | Create | Notification tests |
| `tests/Feature/Api/NotificationApiTest.php` | Create | API tests |
| `tests/Feature/Notifications/SecurityNotificationTest.php` | Create | Security tests |
| `database/factories/NotificationPreferenceFactory.php` | Create | Test factory |

## Implementation Steps

### Step 1: Create NotificationPreference Factory

```php
// database/factories/NotificationPreferenceFactory.php
namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => $this->faker->randomElement(NotificationCategory::cases()),
            'channel' => $this->faker->randomElement(NotificationChannel::cases()),
            'enabled' => $this->faker->boolean(80),
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn() => ['enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn() => ['enabled' => false]);
    }

    public function forCategory(NotificationCategory $category): static
    {
        return $this->state(fn() => ['category' => $category]);
    }

    public function forChannel(NotificationChannel $channel): static
    {
        return $this->state(fn() => ['channel' => $channel]);
    }
}
```

### Step 2: Create Model Tests

```php
// tests/Unit/Models/NotificationPreferenceTest.php
namespace Tests\Unit\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_user(): void
    {
        $preference = NotificationPreference::factory()->create();

        $this->assertInstanceOf(User::class, $preference->user);
    }

    public function test_category_is_cast_to_enum(): void
    {
        $preference = NotificationPreference::factory()
            ->forCategory(NotificationCategory::SECURITY)
            ->create();

        $this->assertInstanceOf(NotificationCategory::class, $preference->category);
        $this->assertEquals(NotificationCategory::SECURITY, $preference->category);
    }

    public function test_channel_is_cast_to_enum(): void
    {
        $preference = NotificationPreference::factory()
            ->forChannel(NotificationChannel::EMAIL)
            ->create();

        $this->assertInstanceOf(NotificationChannel::class, $preference->channel);
        $this->assertEquals(NotificationChannel::EMAIL, $preference->channel);
    }

    public function test_enabled_is_cast_to_boolean(): void
    {
        $preference = NotificationPreference::factory()->enabled()->create();

        $this->assertTrue($preference->enabled);
    }

    public function test_unique_constraint_on_user_category_channel(): void
    {
        $user = User::factory()->create();

        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::SECURITY,
            'channel' => NotificationChannel::EMAIL,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::SECURITY,
            'channel' => NotificationChannel::EMAIL,
        ]);
    }
}
```

### Step 3: Create Service Tests

```php
// tests/Unit/Services/NotificationPreferenceServiceTest.php
namespace Tests\Unit\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationPreferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationPreferenceService::class);
    }

    public function test_initializes_defaults_for_new_user(): void
    {
        $user = User::factory()->create();

        $this->service->initializeDefaultsForUser($user);

        $categories = config('notifications.categories');
        $expectedCount = 0;

        foreach ($categories as $category) {
            $expectedCount += count($category['defaults']);
        }

        $this->assertCount($expectedCount, $user->notificationPreferences);
    }

    public function test_get_grouped_preferences(): void
    {
        $user = User::factory()->create();
        $this->service->initializeDefaultsForUser($user);

        $grouped = $this->service->getGroupedPreferences($user);

        $this->assertArrayHasKey('security', $grouped);
        $this->assertArrayHasKey('communication', $grouped);
        $this->assertTrue($grouped['security']['channels']['email']['forced']);
    }

    public function test_update_preference(): void
    {
        $user = User::factory()->create();
        $this->service->initializeDefaultsForUser($user);

        $preference = $this->service->updatePreference(
            $user,
            NotificationCategory::COMMUNICATION,
            NotificationChannel::EMAIL,
            false
        );

        $this->assertFalse($preference->enabled);
    }

    public function test_cannot_disable_forced_preference(): void
    {
        $user = User::factory()->create();
        $this->service->initializeDefaultsForUser($user);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->updatePreference(
            $user,
            NotificationCategory::SECURITY,
            NotificationChannel::EMAIL,
            false
        );
    }

    public function test_bulk_update_preferences(): void
    {
        $user = User::factory()->create();
        $this->service->initializeDefaultsForUser($user);

        $this->service->bulkUpdatePreferences($user, [
            ['category' => 'communication', 'channel' => 'email', 'enabled' => false],
            ['category' => 'marketing', 'channel' => 'database', 'enabled' => false],
        ]);

        $pref1 = $user->notificationPreferences()
            ->where('category', 'communication')
            ->where('channel', 'email')
            ->first();

        $pref2 = $user->notificationPreferences()
            ->where('category', 'marketing')
            ->where('channel', 'database')
            ->first();

        $this->assertFalse($pref1->enabled);
        $this->assertFalse($pref2->enabled);
    }
}
```

### Step 4: Create Notification Tests

```php
// tests/Feature/Notifications/SecurityNotificationTest.php
namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\Security\LoginAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SecurityNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_alert_notification_is_sent(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $notification = new LoginAlertNotification('192.168.1.1', 'Chrome Browser', 'Vietnam');

        $user->notify($notification);

        Notification::assertSentTo($user, LoginAlertNotification::class);
    }

    public function test_login_alert_contains_correct_data(): void
    {
        $user = User::factory()->create();
        $notification = new LoginAlertNotification('192.168.1.1', 'Chrome Browser', 'Vietnam');

        $data = $notification->toArray($user);

        $this->assertEquals('security', $data['category']);
        $this->assertEquals('New Login Detected', $data['title']);
        $this->assertStringContainsString('Vietnam', $data['message']);
        $this->assertStringContainsString('192.168.1.1', $data['message']);
    }

    public function test_security_notifications_always_send_to_database(): void
    {
        $user = User::factory()->create();

        // Disable all preferences
        $user->notificationPreferences()->update(['enabled' => false]);

        $notification = new LoginAlertNotification('192.168.1.1', 'Chrome Browser', 'Vietnam');
        $channels = $notification->via($user);

        // Security should still include database
        $this->assertContains('database', $channels);
    }

    public function test_login_alert_email_format(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        $notification = new LoginAlertNotification('192.168.1.1', 'Chrome Browser', 'Vietnam');

        $mailMessage = $notification->toMail($user);

        $this->assertEquals('New Login to Your Account', $mailMessage->subject);
        $this->assertStringContainsString('Hello John Doe', $mailMessage->greeting);
    }
}
```

### Step 5: Create API Tests

```php
// tests/Feature/Api/NotificationApiTest.php
namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_notifications(): void
    {
        $this->createNotifications(5);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'type', 'data', 'read_at', 'created_at']],
                'unread_count',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
    }

    public function test_can_filter_by_category(): void
    {
        $this->createNotifications(3, ['data' => ['category' => 'security']]);
        $this->createNotifications(2, ['data' => ['category' => 'marketing']]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications?category=security');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_get_unread_count(): void
    {
        $this->createNotifications(5);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications/unread-count');

        $response->assertOk()
            ->assertJson(['count' => 5]);
    }

    public function test_can_mark_notification_as_read(): void
    {
        $notification = $this->createNotifications(1)->first();

        $response = $this->actingAs($this->user)
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_can_mark_all_as_read(): void
    {
        $this->createNotifications(5);

        $response = $this->actingAs($this->user)
            ->postJson('/api/notifications/read-all');

        $response->assertOk();
        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    public function test_can_delete_notification(): void
    {
        $notification = $this->createNotifications(1)->first();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/notifications/{$notification->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_cannot_access_other_users_notifications(): void
    {
        $otherUser = User::factory()->create();
        $notification = DatabaseNotification::create([
            'id' => \Str::uuid(),
            'type' => 'App\Notifications\Security\LoginAlertNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $otherUser->id,
            'data' => ['category' => 'security', 'title' => 'Test', 'message' => 'Test'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertUnauthorized();
    }

    protected function createNotifications(int $count, array $attributes = []): \Illuminate\Support\Collection
    {
        return collect(range(1, $count))->map(function () use ($attributes) {
            return DatabaseNotification::create(array_merge([
                'id' => \Str::uuid(),
                'type' => 'App\Notifications\Security\LoginAlertNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $this->user->id,
                'data' => ['category' => 'security', 'title' => 'Test', 'message' => 'Test'],
            ], $attributes));
        });
    }
}
```

### Step 6: Run Tests

```bash
# Run all notification tests
php artisan test --filter=Notification

# Run with coverage
php artisan test --filter=Notification --coverage

# Run specific test file
php artisan test tests/Feature/Api/NotificationApiTest.php
```

## Todo List

- [ ] Create NotificationPreferenceFactory
- [ ] Create NotificationPreferenceTest
- [ ] Create NotificationPreferenceServiceTest
- [ ] Create NotificationServiceTest
- [ ] Create SecurityNotificationTest
- [ ] Create NotificationApiTest
- [ ] Run all tests
- [ ] Fix any failing tests
- [ ] Check coverage report
- [ ] Add frontend component tests (optional)

## Success Criteria

- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] 80%+ coverage on notification module
- [ ] No N+1 queries detected
- [ ] Tests run in < 30 seconds
- [ ] Edge cases covered

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Flaky tests | Medium | Use database transactions, freeze time |
| Slow tests | Low | Use in-memory database, mock external services |

## Security Considerations

- Test authorization boundaries
- Verify users can't access others' notifications
- Test rate limiting behavior
- Validate input sanitization

## Next Steps

After testing complete:
1. Deploy to staging
2. Manual QA testing
3. Performance testing with real data volume
4. Deploy to production
