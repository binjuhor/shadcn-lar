<?php

namespace Modules\Ecommerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ecommerce\Models\ProductCategory;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(rand(1, 3), true),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->optional()->paragraph(1),
            'color' => $this->faker->optional()->hexColor(),
            'icon' => $this->faker->optional()->randomElement(['box', 'shirt', 'laptop', 'home', 'gift']),
            'parent_id' => null,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => $this->faker->boolean(90),
            'meta_title' => $this->faker->optional()->sentence(4),
            'meta_description' => $this->faker->optional()->paragraph(1),
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
            'parent_id' => $parentId,
        ]);
    }
}
