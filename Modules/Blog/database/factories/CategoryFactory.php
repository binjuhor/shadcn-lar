<?php

namespace Modules\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Blog\Models\Category;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $categories = [
            ['name' => 'Technology', 'description' => 'Latest tech news and tutorials', 'color' => '#3B82F6', 'icon' => 'cpu'],
            ['name' => 'Design', 'description' => 'UI/UX design and visual design trends', 'color' => '#8B5CF6', 'icon' => 'palette'],
            ['name' => 'Development', 'description' => 'Programming guides and best practices', 'color' => '#10B981', 'icon' => 'code'],
            ['name' => 'Business', 'description' => 'Business strategies and entrepreneurship', 'color' => '#F59E0B', 'icon' => 'briefcase'],
            ['name' => 'Marketing', 'description' => 'Marketing tips and growth strategies', 'color' => '#EF4444', 'icon' => 'megaphone'],
            ['name' => 'Lifestyle', 'description' => 'Life hacks and personal development', 'color' => '#EC4899', 'icon' => 'heart'],
            ['name' => 'Travel', 'description' => 'Travel guides and destination reviews', 'color' => '#14B8A6', 'icon' => 'map'],
            ['name' => 'Food', 'description' => 'Recipes and culinary adventures', 'color' => '#F97316', 'icon' => 'utensils'],
        ];

        $category = $this->faker->randomElement($categories);

        return [
            'name' => $category['name'].' '.$this->faker->unique()->numberBetween(1, 1000),
            'description' => $category['description'],
            'color' => $category['color'],
            'icon' => $category['icon'],
            'parent_id' => null,
            'is_active' => $this->faker->boolean(90),
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

    public function withParent(?int $parentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId ?? Category::factory(),
        ]);
    }

    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color,
        ]);
    }
}
