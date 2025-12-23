<?php

namespace Modules\Notification\Tests\Unit\Enums;

use Modules\Notification\Enums\NotificationChannel;
use PHPUnit\Framework\TestCase;

class NotificationChannelTest extends TestCase
{
    public function test_has_expected_cases(): void
    {
        $cases = NotificationChannel::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(NotificationChannel::DATABASE, $cases);
        $this->assertContains(NotificationChannel::EMAIL, $cases);
        $this->assertContains(NotificationChannel::SMS, $cases);
        $this->assertContains(NotificationChannel::PUSH, $cases);
    }

    public function test_has_correct_values(): void
    {
        $this->assertEquals('database', NotificationChannel::DATABASE->value);
        $this->assertEquals('email', NotificationChannel::EMAIL->value);
        $this->assertEquals('sms', NotificationChannel::SMS->value);
        $this->assertEquals('push', NotificationChannel::PUSH->value);
    }

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertEquals('In-App', NotificationChannel::DATABASE->label());
        $this->assertEquals('Email', NotificationChannel::EMAIL->label());
        $this->assertEquals('SMS', NotificationChannel::SMS->label());
        $this->assertEquals('Push', NotificationChannel::PUSH->label());
    }

    public function test_description_returns_string(): void
    {
        foreach (NotificationChannel::cases() as $channel) {
            $this->assertIsString($channel->description());
            $this->assertNotEmpty($channel->description());
        }
    }

    public function test_icon_returns_string(): void
    {
        foreach (NotificationChannel::cases() as $channel) {
            $this->assertIsString($channel->icon());
            $this->assertNotEmpty($channel->icon());
        }
    }

    public function test_driver_returns_correct_driver(): void
    {
        $this->assertEquals('database', NotificationChannel::DATABASE->driver());
        $this->assertEquals('mail', NotificationChannel::EMAIL->driver());
        $this->assertEquals('vonage', NotificationChannel::SMS->driver());
        $this->assertEquals('fcm', NotificationChannel::PUSH->driver());
    }

    public function test_can_be_created_from_value(): void
    {
        $channel = NotificationChannel::from('email');

        $this->assertEquals(NotificationChannel::EMAIL, $channel);
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $channel = NotificationChannel::tryFrom('invalid');

        $this->assertNull($channel);
    }
}
