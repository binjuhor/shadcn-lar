<?php

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Notification\{
    Enums\NotificationCategory,
    Enums\NotificationChannel,
    Models\NotificationTemplate,
    Notifications\GenericNotification
};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class AdminNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->setupPermissions();

        $adminRole = Role::create(['name' => 'super-admin']);
        $adminRole->givePermissionTo(Permission::all());

        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        $this->user = User::factory()->create();
    }

    protected function setupPermissions(): void
    {
        $permissions = [
            'notifications.templates.view',
            'notifications.templates.create',
            'notifications.templates.edit',
            'notifications.templates.delete',
            'notifications.send',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    public function test_admin_can_view_send_notification_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard.notifications.send'));

        $response->assertStatus(200);
    }

    public function test_admin_can_send_notification_to_users(): void
    {
        Notification::fake();

        $recipients = User::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.notifications.send.store'), [
                'recipient_type' => 'users',
                'user_ids' => $recipients->pluck('id')->toArray(),
                'use_template' => false,
                'title' => 'Test Notification',
                'message' => 'This is a test message',
                'category' => NotificationCategory::SYSTEM->value,
                'channels' => [NotificationChannel::DATABASE->value],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Notification sent to 3 user(s).']);

        Notification::assertSentTo($recipients, GenericNotification::class);
    }

    public function test_admin_can_send_notification_to_role(): void
    {
        Notification::fake();

        $testRole = Role::create(['name' => 'tester']);
        $testers = User::factory()->count(2)->create();
        foreach ($testers as $tester) {
            $tester->assignRole($testRole);
        }

        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.notifications.send.store'), [
                'recipient_type' => 'roles',
                'role_ids' => [$testRole->id],
                'use_template' => false,
                'title' => 'Tester Notification',
                'message' => 'For testers only',
                'category' => NotificationCategory::SYSTEM->value,
                'channels' => [NotificationChannel::DATABASE->value],
            ]);

        $response->assertStatus(200);

        Notification::assertSentTo($testers, GenericNotification::class);
    }

    public function test_admin_can_search_users(): void
    {
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->actingAs($this->admin)
            ->getJson(route('dashboard.notifications.search-users', ['q' => 'John']));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'users');
        $response->assertJsonPath('users.0.label', 'John Doe');
    }

    public function test_regular_user_cannot_send_notifications(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.notifications.send'));

        $response->assertStatus(403);
    }

    public function test_send_notification_validates_recipient_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.notifications.send.store'), [
                'recipient_type' => 'invalid',
                'use_template' => false,
                'title' => 'Test',
                'message' => 'Test',
                'category' => NotificationCategory::SYSTEM->value,
                'channels' => [NotificationChannel::DATABASE->value],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipient_type']);
    }

    public function test_admin_can_send_notification_from_template(): void
    {
        Notification::fake();

        $template = NotificationTemplate::factory()->create([
            'subject' => 'Hello {{ user_name }}',
            'body' => 'Welcome {{ user_name }}!',
            'category' => NotificationCategory::COMMUNICATION,
            'channels' => ['database'],
            'is_active' => true,
        ]);

        $recipient = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.notifications.send.store'), [
                'recipient_type' => 'users',
                'user_ids' => [$recipient->id],
                'use_template' => true,
                'template_id' => $template->id,
                'template_variables' => ['user_name' => 'John'],
            ]);

        $response->assertStatus(200);

        Notification::assertSentTo($recipient, GenericNotification::class);
    }
}
