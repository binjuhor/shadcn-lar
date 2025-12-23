<?php

namespace Modules\Notification\Tests\Unit\Enums;

use Modules\Notification\Enums\NotificationCategory;
use PHPUnit\Framework\TestCase;

class NotificationCategoryTest extends TestCase
{
    public function test_has_expected_cases(): void
    {
        $cases = NotificationCategory::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(NotificationCategory::COMMUNICATION, $cases);
        $this->assertContains(NotificationCategory::MARKETING, $cases);
        $this->assertContains(NotificationCategory::SECURITY, $cases);
        $this->assertContains(NotificationCategory::SYSTEM, $cases);
        $this->assertContains(NotificationCategory::TRANSACTIONAL, $cases);
    }

    public function test_has_correct_values(): void
    {
        $this->assertEquals('communication', NotificationCategory::COMMUNICATION->value);
        $this->assertEquals('marketing', NotificationCategory::MARKETING->value);
        $this->assertEquals('security', NotificationCategory::SECURITY->value);
        $this->assertEquals('system', NotificationCategory::SYSTEM->value);
        $this->assertEquals('transactional', NotificationCategory::TRANSACTIONAL->value);
    }

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertEquals('Communication', NotificationCategory::COMMUNICATION->label());
        $this->assertEquals('Marketing', NotificationCategory::MARKETING->label());
        $this->assertEquals('Security', NotificationCategory::SECURITY->label());
        $this->assertEquals('System Alerts', NotificationCategory::SYSTEM->label());
        $this->assertEquals('Transactional', NotificationCategory::TRANSACTIONAL->label());
    }

    public function test_description_returns_string(): void
    {
        foreach (NotificationCategory::cases() as $category) {
            $this->assertIsString($category->description());
            $this->assertNotEmpty($category->description());
        }
    }

    public function test_icon_returns_string(): void
    {
        foreach (NotificationCategory::cases() as $category) {
            $this->assertIsString($category->icon());
            $this->assertNotEmpty($category->icon());
        }
    }

    public function test_can_be_created_from_value(): void
    {
        $category = NotificationCategory::from('security');

        $this->assertEquals(NotificationCategory::SECURITY, $category);
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $category = NotificationCategory::tryFrom('invalid');

        $this->assertNull($category);
    }
}
