<?php

namespace Modules\Notification\Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationPreference;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_notification_preference(): void
    {
        $user = User::factory()->create();

        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'category' => NotificationCategory::MARKETING,
            'channel' => NotificationChannel::EMAIL,
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'category' => 'marketing',
            'channel' => 'email',
            'enabled' => true,
        ]);
    }

    public function test_casts_category_to_enum(): void
    {
        $preference = NotificationPreference::factory()->create([
            'category' => NotificationCategory::SECURITY,
        ]);

        $this->assertInstanceOf(NotificationCategory::class, $preference->category);
        $this->assertEquals(NotificationCategory::SECURITY, $preference->category);
    }

    public function test_casts_channel_to_enum(): void
    {
        $preference = NotificationPreference::factory()->create([
            'channel' => NotificationChannel::DATABASE,
        ]);

        $this->assertInstanceOf(NotificationChannel::class, $preference->channel);
        $this->assertEquals(NotificationChannel::DATABASE, $preference->channel);
    }

    public function test_casts_enabled_to_boolean(): void
    {
        $preference = NotificationPreference::factory()->create([
            'enabled' => 1,
        ]);

        $this->assertIsBool($preference->enabled);
        $this->assertTrue($preference->enabled);
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $preference = NotificationPreference::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $preference->user);
        $this->assertEquals($user->id, $preference->user->id);
    }

    public function test_factory_creates_valid_preference(): void
    {
        $preference = NotificationPreference::factory()->create();

        $this->assertNotNull($preference->id);
        $this->assertNotNull($preference->user_id);
        $this->assertInstanceOf(NotificationCategory::class, $preference->category);
        $this->assertInstanceOf(NotificationChannel::class, $preference->channel);
        $this->assertIsBool($preference->enabled);
    }

    public function test_factory_enabled_state(): void
    {
        $preference = NotificationPreference::factory()->enabled()->create();

        $this->assertTrue($preference->enabled);
    }

    public function test_factory_disabled_state(): void
    {
        $preference = NotificationPreference::factory()->disabled()->create();

        $this->assertFalse($preference->enabled);
    }

    public function test_factory_for_category_state(): void
    {
        $preference = NotificationPreference::factory()
            ->forCategory(NotificationCategory::TRANSACTIONAL)
            ->create();

        $this->assertEquals(NotificationCategory::TRANSACTIONAL, $preference->category);
    }

    public function test_factory_for_channel_state(): void
    {
        $preference = NotificationPreference::factory()
            ->forChannel(NotificationChannel::PUSH)
            ->create();

        $this->assertEquals(NotificationChannel::PUSH, $preference->channel);
    }

    public function test_unique_constraint_on_user_category_channel(): void
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => NotificationCategory::MARKETING,
            'channel' => NotificationChannel::EMAIL,
            'enabled' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => NotificationCategory::MARKETING,
            'channel' => NotificationChannel::EMAIL,
            'enabled' => false,
        ]);
    }
}
