<?php

namespace Modules\Notification\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationPreference;

/**
 * @extends Factory<NotificationPreference>
 */
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
        return $this->state(fn () => ['enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function forCategory(NotificationCategory $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }

    public function forChannel(NotificationChannel $channel): static
    {
        return $this->state(fn () => ['channel' => $channel]);
    }
}
