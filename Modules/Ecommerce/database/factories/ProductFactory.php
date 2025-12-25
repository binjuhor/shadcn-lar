<?php

namespace Modules\Ecommerce\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ecommerce\Models\Product;
use Modules\Ecommerce\Models\ProductCategory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 10, 500);
        $salePrice = $this->faker->boolean(30) ? $price * $this->faker->randomFloat(2, 0.5, 0.9) : null;

        return [
            'name' => $this->faker->words(rand(2, 5), true),
            'slug' => $this->faker->unique()->slug(3),
            'description' => $this->faker->paragraph(2),
            'content' => $this->generateContent(),
            'sku' => 'PRD-'.strtoupper($this->faker->unique()->bothify('??####')),
            'price' => $price,
            'sale_price' => $salePrice,
            'cost' => $price * $this->faker->randomFloat(2, 0.3, 0.6),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'low_stock_threshold' => 5,
            'track_inventory' => true,
            'status' => $this->faker->randomElement(['draft', 'active', 'archived']),
            'featured_image' => null,
            'images' => null,
            'category_id' => ProductCategory::factory(),
            'user_id' => User::factory(),
            'views_count' => $this->faker->numberBetween(0, 1000),
            'sales_count' => $this->faker->numberBetween(0, 100),
            'is_featured' => $this->faker->boolean(20),
            'meta_title' => $this->faker->optional()->sentence(6),
            'meta_description' => $this->faker->optional()->paragraph(1),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function onSale(): static
    {
        return $this->state(function (array $attributes) {
            $price = $attributes['price'] ?? 100;

            return [
                'sale_price' => $price * 0.8,
            ];
        });
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
            'track_inventory' => true,
        ]);
    }

    public function withCategory(?int $categoryId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    private function generateContent(): string
    {
        $paragraphs = [];
        $numParagraphs = rand(3, 8);

        $paragraphs[] = '<h2>'.$this->faker->sentence(4).'</h2>';
        $paragraphs[] = '<p>'.$this->faker->paragraph(rand(3, 6)).'</p>';

        for ($i = 0; $i < $numParagraphs; $i++) {
            $paragraphs[] = '<p>'.$this->faker->paragraph(rand(3, 6)).'</p>';
        }

        return implode("\n\n", $paragraphs);
    }
}
