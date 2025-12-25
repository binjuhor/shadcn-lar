<?php

namespace Modules\Notification\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notification\Enums\NotificationCategory;
use Modules\Notification\Enums\NotificationChannel;
use Modules\Notification\Models\NotificationTemplate;

class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        $categories = NotificationCategory::cases();
        $channels = array_map(fn ($c) => $c->value, NotificationChannel::cases());

        return [
            'name' => fake()->words(3, true),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'category' => fake()->randomElement($categories),
            'channels' => fake()->randomElements($channels, fake()->numberBetween(1, 3)),
            'variables' => ['user_name', 'action_url'],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forCategory(NotificationCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    public function forChannel(NotificationChannel $channel): static
    {
        return $this->state(fn (array $attributes) => [
            'channels' => [$channel->value],
        ]);
    }
}
