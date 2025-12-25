<?php

namespace Modules\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Blog\Models\Tag;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $tags = [
            ['name' => 'React', 'color' => '#61DAFB'],
            ['name' => 'Vue', 'color' => '#4FC08D'],
            ['name' => 'Angular', 'color' => '#DD0031'],
            ['name' => 'Laravel', 'color' => '#FF2D20'],
            ['name' => 'TypeScript', 'color' => '#3178C6'],
            ['name' => 'PHP', 'color' => '#777BB4'],
            ['name' => 'JavaScript', 'color' => '#F7DF1E'],
            ['name' => 'Python', 'color' => '#3776AB'],
            ['name' => 'CSS', 'color' => '#1572B6'],
            ['name' => 'HTML', 'color' => '#E34F26'],
            ['name' => 'Tutorial', 'color' => '#6366F1'],
            ['name' => 'Guide', 'color' => '#EC4899'],
            ['name' => 'Tips', 'color' => '#8B5CF6'],
            ['name' => 'News', 'color' => '#EF4444'],
            ['name' => 'Opinion', 'color' => '#F59E0B'],
            ['name' => 'Review', 'color' => '#10B981'],
            ['name' => 'Beginner', 'color' => '#14B8A6'],
            ['name' => 'Advanced', 'color' => '#F97316'],
            ['name' => 'Best Practices', 'color' => '#06B6D4'],
            ['name' => 'Performance', 'color' => '#84CC16'],
        ];

        $tag = $this->faker->randomElement($tags);

        return [
            'name' => $tag['name'].' '.$this->faker->unique()->numberBetween(1, 1000),
            'description' => $this->faker->optional()->sentence(),
            'color' => $tag['color'],
            'is_active' => $this->faker->boolean(95),
            'usage_count' => $this->faker->numberBetween(0, 500),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(100, 1000),
        ]);
    }

    public function unused(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => 0,
        ]);
    }

    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color,
        ]);
    }
}
