<?php

namespace Modules\Notification\Tests\Unit\Services;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Notification\{
    Enums\NotificationCategory,
    Enums\NotificationChannel,
    Models\NotificationTemplate,
    Notifications\GenericNotification,
    Services\NotificationService
};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService;
    }

    public function test_send_to_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->service->sendToUser(
            user: $user,
            title: 'Test Notification',
            message: 'This is a test',
            category: NotificationCategory::SYSTEM,
        );

        Notification::assertSentTo($user, GenericNotification::class);
    }

    public function test_send_to_users(): void
    {
        Notification::fake();

        $users = User::factory()->count(3)->create();

        $this->service->sendToUsers(
            users: $users,
            title: 'Bulk Notification',
            message: 'Sent to all',
            category: NotificationCategory::MARKETING,
        );

        Notification::assertSentTo($users, GenericNotification::class);
    }

    public function test_send_to_role(): void
    {
        Notification::fake();

        $role = Role::create(['name' => 'admin']);
        $adminUser = User::factory()->create();
        $adminUser->assignRole($role);

        $regularUser = User::factory()->create();

        $this->service->sendToRole(
            role: 'admin',
            title: 'Admin Only',
            message: 'For admins',
            category: NotificationCategory::SYSTEM,
        );

        Notification::assertSentTo($adminUser, GenericNotification::class);
        Notification::assertNotSentTo($regularUser, GenericNotification::class);
    }

    public function test_broadcast(): void
    {
        Notification::fake();

        $users = User::factory()->count(5)->create();

        $this->service->broadcast(
            title: 'Broadcast',
            message: 'To everyone',
            category: NotificationCategory::COMMUNICATION,
        );

        Notification::assertSentTo($users, GenericNotification::class);
    }

    public function test_send_from_template(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $template = NotificationTemplate::factory()->create([
            'subject' => 'Hello {{ name }}',
            'body' => 'Welcome {{ name }}!',
            'category' => NotificationCategory::COMMUNICATION,
            'channels' => ['database'],
        ]);

        $this->service->sendFromTemplate(
            template: $template,
            recipients: $user,
            variables: ['name' => 'John'],
        );

        Notification::assertSentTo($user, GenericNotification::class);
    }

    public function test_send_from_template_to_multiple_users(): void
    {
        Notification::fake();

        $users = User::factory()->count(3)->create();
        $template = NotificationTemplate::factory()->create([
            'subject' => 'Announcement',
            'body' => 'Important update!',
            'category' => NotificationCategory::SYSTEM,
        ]);

        $this->service->sendFromTemplate(
            template: $template,
            recipients: $users,
        );

        Notification::assertSentTo($users, GenericNotification::class);
    }

    public function test_send_from_template_by_slug(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        NotificationTemplate::factory()->create([
            'slug' => 'welcome-email',
            'subject' => 'Welcome!',
            'body' => 'Hello there!',
            'category' => NotificationCategory::COMMUNICATION,
            'is_active' => true,
        ]);

        $this->service->sendFromTemplateBySlug(
            slug: 'welcome-email',
            recipients: $user,
        );

        Notification::assertSentTo($user, GenericNotification::class);
    }

    public function test_send_from_template_by_slug_fails_for_inactive(): void
    {
        $user = User::factory()->create();
        NotificationTemplate::factory()->create([
            'slug' => 'inactive-template',
            'is_active' => false,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->sendFromTemplateBySlug(
            slug: 'inactive-template',
            recipients: $user,
        );
    }

    public function test_send_with_custom_channels(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->service->sendToUser(
            user: $user,
            title: 'Test',
            message: 'Test',
            category: NotificationCategory::SECURITY,
            channels: [NotificationChannel::DATABASE, NotificationChannel::EMAIL],
        );

        Notification::assertSentTo($user, GenericNotification::class, function ($notification) use ($user) {
            $channels = $notification->via($user);

            return in_array('database', $channels) && in_array('mail', $channels);
        });
    }

    public function test_send_with_action_url_and_label(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->service->sendToUser(
            user: $user,
            title: 'Test',
            message: 'Test',
            category: NotificationCategory::TRANSACTIONAL,
            actionUrl: 'https://example.com/order/123',
            actionLabel: 'View Order',
        );

        Notification::assertSentTo($user, GenericNotification::class, function ($notification) use ($user) {
            $data = $notification->toArray($user);

            return $data['action_url'] === 'https://example.com/order/123'
                && $data['action_label'] === 'View Order';
        });
    }
}
