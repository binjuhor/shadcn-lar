<?php

namespace Modules\Notification\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\{
    Enums\NotificationCategory,
    Enums\NotificationChannel,
    Models\NotificationTemplate
};
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_notification_template(): void
    {
        $template = NotificationTemplate::create([
            'name' => 'Welcome Email',
            'subject' => 'Welcome to {{ app_name }}',
            'body' => 'Hello {{ user_name }}, welcome!',
            'category' => NotificationCategory::COMMUNICATION,
            'channels' => [NotificationChannel::DATABASE->value, NotificationChannel::EMAIL->value],
            'variables' => ['app_name', 'user_name'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('notification_templates', [
            'name' => 'Welcome Email',
            'category' => 'communication',
        ]);
    }

    public function test_generates_slug_automatically(): void
    {
        $template = NotificationTemplate::create([
            'name' => 'Order Confirmation Email',
            'subject' => 'Order Confirmed',
            'body' => 'Your order is confirmed.',
            'category' => NotificationCategory::TRANSACTIONAL,
            'channels' => [NotificationChannel::EMAIL->value],
        ]);

        $this->assertEquals('order-confirmation-email', $template->slug);
    }

    public function test_generates_unique_slug_when_duplicate(): void
    {
        NotificationTemplate::create([
            'name' => 'Test Template',
            'subject' => 'Test',
            'body' => 'Test body',
            'category' => NotificationCategory::SYSTEM,
            'channels' => [NotificationChannel::DATABASE->value],
        ]);

        $template2 = NotificationTemplate::create([
            'name' => 'Test Template',
            'subject' => 'Test 2',
            'body' => 'Test body 2',
            'category' => NotificationCategory::SYSTEM,
            'channels' => [NotificationChannel::DATABASE->value],
        ]);

        $this->assertEquals('test-template-1', $template2->slug);
    }

    public function test_casts_category_to_enum(): void
    {
        $template = NotificationTemplate::factory()->create([
            'category' => NotificationCategory::SECURITY,
        ]);

        $this->assertInstanceOf(NotificationCategory::class, $template->category);
    }

    public function test_casts_channels_to_array(): void
    {
        $template = NotificationTemplate::factory()->create([
            'channels' => ['database', 'email'],
        ]);

        $this->assertIsArray($template->channels);
        $this->assertCount(2, $template->channels);
    }

    public function test_casts_variables_to_array(): void
    {
        $template = NotificationTemplate::factory()->create([
            'variables' => ['user_name', 'order_id'],
        ]);

        $this->assertIsArray($template->variables);
    }

    public function test_scope_active(): void
    {
        NotificationTemplate::factory()->create(['is_active' => true]);
        NotificationTemplate::factory()->create(['is_active' => false]);

        $activeTemplates = NotificationTemplate::active()->get();

        $this->assertCount(1, $activeTemplates);
        $this->assertTrue($activeTemplates->first()->is_active);
    }

    public function test_scope_by_category(): void
    {
        NotificationTemplate::factory()->create(['category' => NotificationCategory::SECURITY]);
        NotificationTemplate::factory()->create(['category' => NotificationCategory::MARKETING]);

        $securityTemplates = NotificationTemplate::byCategory(NotificationCategory::SECURITY)->get();

        $this->assertCount(1, $securityTemplates);
        $this->assertEquals(NotificationCategory::SECURITY, $securityTemplates->first()->category);
    }

    public function test_scope_by_channel(): void
    {
        NotificationTemplate::factory()->create(['channels' => ['database']]);
        NotificationTemplate::factory()->create(['channels' => ['email']]);

        $databaseTemplates = NotificationTemplate::byChannel(NotificationChannel::DATABASE)->get();

        $this->assertCount(1, $databaseTemplates);
    }

    public function test_render_replaces_variables(): void
    {
        $template = NotificationTemplate::factory()->create([
            'subject' => 'Hello {{ user_name }}',
            'body' => 'Welcome to {{ app_name }}, {{ user_name }}!',
        ]);

        $rendered = $template->render([
            'user_name' => 'John',
            'app_name' => 'MyApp',
        ]);

        $this->assertEquals('Hello John', $rendered['subject']);
        $this->assertEquals('Welcome to MyApp, John!', $rendered['body']);
    }

    public function test_supports_channel_returns_true_for_supported(): void
    {
        $template = NotificationTemplate::factory()->create([
            'channels' => ['database', 'email'],
        ]);

        $this->assertTrue($template->supportsChannel(NotificationChannel::DATABASE));
        $this->assertTrue($template->supportsChannel(NotificationChannel::EMAIL));
        $this->assertTrue($template->supportsChannel('database'));
    }

    public function test_supports_channel_returns_false_for_unsupported(): void
    {
        $template = NotificationTemplate::factory()->create([
            'channels' => ['database'],
        ]);

        $this->assertFalse($template->supportsChannel(NotificationChannel::SMS));
        $this->assertFalse($template->supportsChannel('push'));
    }

    public function test_soft_deletes(): void
    {
        $template = NotificationTemplate::factory()->create();
        $templateId = $template->id;

        $template->delete();

        $this->assertSoftDeleted('notification_templates', ['id' => $templateId]);
        $this->assertNull(NotificationTemplate::find($templateId));
        $this->assertNotNull(NotificationTemplate::withTrashed()->find($templateId));
    }
}
