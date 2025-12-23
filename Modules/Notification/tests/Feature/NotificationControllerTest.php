<?php

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Notifications\GenericNotification;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    public function test_user_can_view_notifications_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.notifications.index'));

        $response->assertStatus(200);
    }

    public function test_user_can_get_unread_count(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.notifications.unread-count'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['count']);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        Notification::send($this->user, new GenericNotification(
            title: 'Test',
            message: 'Test',
            category: NotificationCategory::SYSTEM,
        ));

        $notification = $this->user->notifications()->first();

        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.notifications.read', $notification->id));

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertNotNull($this->user->notifications()->first()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Notification::send($this->user, new GenericNotification(
            title: 'Test 1',
            message: 'Test',
            category: NotificationCategory::SYSTEM,
        ));

        Notification::send($this->user, new GenericNotification(
            title: 'Test 2',
            message: 'Test',
            category: NotificationCategory::MARKETING,
        ));

        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.notifications.read-all'));

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    public function test_user_can_delete_notification(): void
    {
        Notification::send($this->user, new GenericNotification(
            title: 'Test',
            message: 'Test',
            category: NotificationCategory::SYSTEM,
        ));

        $notification = $this->user->notifications()->first();

        $response = $this->actingAs($this->user)
            ->deleteJson(route('dashboard.notifications.destroy', $notification->id));

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals(0, $this->user->notifications()->count());
    }

    public function test_user_cannot_access_other_users_notifications(): void
    {
        $otherUser = User::factory()->create();

        Notification::send($otherUser, new GenericNotification(
            title: 'Private',
            message: 'Private message',
            category: NotificationCategory::SECURITY,
        ));

        $notification = $otherUser->notifications()->first();

        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.notifications.read', $notification->id));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson(route('dashboard.notifications.unread-count'));

        $response->assertStatus(401);
    }
}
