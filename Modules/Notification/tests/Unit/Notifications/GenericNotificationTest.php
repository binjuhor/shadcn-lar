<?php

namespace Modules\Notification\Tests\Unit\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Notifications\GenericNotification;
use Tests\TestCase;

class GenericNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_generic_notification(): void
    {
        $notification = new GenericNotification(
            title: 'Test Title',
            message: 'Test message',
            category: NotificationCategory::SYSTEM,
        );

        $this->assertInstanceOf(GenericNotification::class, $notification);
    }

    public function test_via_returns_correct_channels(): void
    {
        $notification = new GenericNotification(
            title: 'Test',
            message: 'Test',
            category: NotificationCategory::SYSTEM,
            channels: [NotificationChannel::DATABASE, NotificationChannel::EMAIL],
        );

        $user = User::factory()->create();
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $notification = new GenericNotification(
            title: 'Test Title',
            message: 'Test message',
            category: NotificationCategory::MARKETING,
            actionUrl: 'https://example.com',
            actionLabel: 'Click here',
            icon: 'sparkles',
        );

        $user = User::factory()->create();
        $array = $notification->toArray($user);

        $this->assertEquals('Test Title', $array['title']);
        $this->assertEquals('Test message', $array['message']);
        $this->assertEquals('marketing', $array['category']);
        $this->assertEquals('https://example.com', $array['action_url']);
        $this->assertEquals('Click here', $array['action_label']);
        $this->assertEquals('sparkles', $array['icon']);
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $notification = new GenericNotification(
            title: 'Test Title',
            message: 'Test message',
            category: NotificationCategory::COMMUNICATION,
            actionUrl: 'https://example.com',
            actionLabel: 'View',
        );

        $user = User::factory()->create();
        $mail = $notification->toMail($user);

        $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $mail);
    }

    public function test_from_template_creates_notification(): void
    {
        $template = NotificationTemplate::factory()->create([
            'subject' => 'Hello {{ user_name }}',
            'body' => 'Welcome {{ user_name }}!',
            'category' => NotificationCategory::COMMUNICATION,
            'channels' => ['database', 'email'],
        ]);

        $notification = GenericNotification::fromTemplate(
            template: $template,
            variables: ['user_name' => 'John'],
            actionUrl: 'https://example.com',
        );

        $user = User::factory()->create();
        $array = $notification->toArray($user);

        $this->assertEquals('Hello John', $array['title']);
        $this->assertEquals('Welcome John!', $array['message']);
    }

    public function test_defaults_to_database_channel(): void
    {
        $notification = new GenericNotification(
            title: 'Test',
            message: 'Test',
            category: NotificationCategory::SYSTEM,
        );

        $user = User::factory()->create();
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
    }

    public function test_uses_category_icon_when_not_provided(): void
    {
        $notification = new GenericNotification(
            title: 'Test',
            message: 'Test',
            category: NotificationCategory::SECURITY,
        );

        $user = User::factory()->create();
        $array = $notification->toArray($user);

        $this->assertEquals(NotificationCategory::SECURITY->icon(), $array['icon']);
    }

    public function test_to_database_returns_same_as_to_array(): void
    {
        $notification = new GenericNotification(
            title: 'Test',
            message: 'Test',
            category: NotificationCategory::TRANSACTIONAL,
        );

        $user = User::factory()->create();

        $this->assertEquals(
            $notification->toArray($user),
            $notification->toDatabase($user)
        );
    }
}
