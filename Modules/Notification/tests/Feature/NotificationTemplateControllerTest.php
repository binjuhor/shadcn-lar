<?php

namespace Modules\Notification\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\{
    Enums\NotificationCategory,
    Enums\NotificationChannel,
    Models\NotificationTemplate
};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class NotificationTemplateControllerTest extends TestCase
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

    public function test_admin_can_view_templates_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard.notifications.templates.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_view_create_template_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard.notifications.templates.create'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_template(): void
    {
        $data = [
            'name' => 'Test Template',
            'subject' => 'Test Subject',
            'body' => 'Test body content',
            'category' => NotificationCategory::SYSTEM->value,
            'channels' => [NotificationChannel::DATABASE->value],
            'variables' => ['user_name'],
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('dashboard.notifications.templates.store'), $data);

        $response->assertRedirect(route('dashboard.notifications.templates.index'));

        $this->assertDatabaseHas('notification_templates', [
            'name' => 'Test Template',
            'category' => 'system',
        ]);
    }

    public function test_admin_can_view_edit_template_page(): void
    {
        $template = NotificationTemplate::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard.notifications.templates.edit', $template));

        $response->assertStatus(200);
    }

    public function test_admin_can_update_template(): void
    {
        $template = NotificationTemplate::factory()->create([
            'name' => 'Old Name',
        ]);

        $data = [
            'name' => 'Updated Name',
            'subject' => 'Updated Subject',
            'body' => 'Updated body',
            'category' => NotificationCategory::MARKETING->value,
            'channels' => [NotificationChannel::EMAIL->value],
            'is_active' => false,
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('dashboard.notifications.templates.update', $template), $data);

        $response->assertRedirect(route('dashboard.notifications.templates.index'));

        $this->assertDatabaseHas('notification_templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_template(): void
    {
        $template = NotificationTemplate::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('dashboard.notifications.templates.destroy', $template));

        $response->assertRedirect(route('dashboard.notifications.templates.index'));

        $this->assertSoftDeleted('notification_templates', ['id' => $template->id]);
    }

    public function test_admin_can_toggle_template_status(): void
    {
        $template = NotificationTemplate::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.notifications.templates.toggle-status', $template));

        $response->assertStatus(200);
        $response->assertJson(['is_active' => false]);

        $template->refresh();
        $this->assertFalse($template->is_active);
    }

    public function test_regular_user_cannot_access_templates(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.notifications.templates.index'));

        $response->assertStatus(403);
    }

    public function test_create_template_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('dashboard.notifications.templates.store'), []);

        $response->assertSessionHasErrors(['name', 'subject', 'body', 'category', 'channels']);
    }
}
