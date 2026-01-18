<?php

namespace Modules\Blog\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Blog\Models\{Category, Post};

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $publishedAt = $this->faker->boolean(70) ? $this->faker->dateTimeBetween('-6 months', 'now') : null;

        return [
            'title' => $this->faker->sentence(rand(4, 8)),
            'excerpt' => $this->faker->paragraph(2),
            'content' => $this->generateContent(),
            'featured_image' => $this->faker->imageUrl(1200, 630, 'business', true),
            'status' => $this->faker->randomElement(['draft', 'published', 'scheduled', 'archived']),
            'published_at' => $publishedAt,
            'meta_title' => $this->faker->optional()->sentence(6),
            'meta_description' => $this->faker->optional()->paragraph(1),
            'category_id' => Category::factory(),
            'user_id' => User::factory(),
            'views_count' => $this->faker->numberBetween(0, 10000),
            'is_featured' => $this->faker->boolean(20),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'published_at' => $this->faker->dateTimeBetween('now', '+2 months'),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function withCategory(?int $categoryId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    public function withUser(?int $userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    private function generateContent(): string
    {
        $paragraphs = [];
        $numParagraphs = rand(5, 15);

        $paragraphs[] = '<h2>'.$this->faker->sentence(4).'</h2>';
        $paragraphs[] = '<p>'.$this->faker->paragraph(rand(4, 8)).'</p>';

        for ($i = 0; $i < $numParagraphs; $i++) {
            if ($i % 3 === 0 && $i !== 0) {
                $paragraphs[] = '<h2>'.$this->faker->sentence(rand(3, 6)).'</h2>';
            }

            $paragraphs[] = '<p>'.$this->faker->paragraph(rand(4, 8)).'</p>';

            if ($i % 5 === 0 && $i !== 0) {
                $listItems = array_map(
                    fn ($item) => '<li>'.$this->faker->sentence(rand(3, 8)).'</li>',
                    range(1, rand(3, 5))
                );
                $paragraphs[] = '<ul>'.implode('', $listItems).'</ul>';
            }
        }

        return implode("\n\n", $paragraphs);
    }
}
