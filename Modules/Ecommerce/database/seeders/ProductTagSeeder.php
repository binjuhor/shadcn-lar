<?php

namespace Modules\Ecommerce\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Ecommerce\Models\ProductTag;

class ProductTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'New Arrival', 'slug' => 'new-arrival'],
            ['name' => 'Best Seller', 'slug' => 'best-seller'],
            ['name' => 'Limited Edition', 'slug' => 'limited-edition'],
            ['name' => 'Sale', 'slug' => 'sale'],
            ['name' => 'Eco Friendly', 'slug' => 'eco-friendly'],
            ['name' => 'Premium', 'slug' => 'premium'],
            ['name' => 'Budget', 'slug' => 'budget'],
            ['name' => 'Popular', 'slug' => 'popular'],
        ];

        foreach ($tags as $tag) {
            ProductTag::updateOrCreate(
                ['slug' => $tag['slug']],
                $tag
            );
        }
    }
}
