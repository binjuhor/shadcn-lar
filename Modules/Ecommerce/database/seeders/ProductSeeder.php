<?php

namespace Modules\Ecommerce\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Ecommerce\Models\{Product, ProductCategory, ProductTag};

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $categories = ProductCategory::all();
        $tags = ProductTag::all();

        if (! $user || $categories->isEmpty()) {
            $this->command->warn('Please create a user and categories first');

            return;
        }

        $products = [
            [
                'name' => 'Wireless Bluetooth Headphones',
                'description' => 'High-quality wireless headphones with noise cancellation',
                'content' => '<p>Experience premium sound quality with our wireless Bluetooth headphones featuring active noise cancellation, 30-hour battery life, and comfortable over-ear design.</p>',
                'price' => 149.99,
                'sale_price' => 129.99,
                'cost' => 75.00,
                'stock_quantity' => 50,
                'low_stock_threshold' => 10,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => true,
                'category_id' => $categories->where('slug', 'electronics')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Smart Fitness Watch',
                'description' => 'Track your fitness goals with this advanced smartwatch',
                'content' => '<p>Stay connected and track your health with GPS, heart rate monitoring, sleep tracking, and 7-day battery life.</p>',
                'price' => 299.99,
                'cost' => 150.00,
                'stock_quantity' => 30,
                'low_stock_threshold' => 5,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => true,
                'category_id' => $categories->where('slug', 'electronics')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Cotton T-Shirt - Classic Fit',
                'description' => '100% organic cotton t-shirt in various colors',
                'content' => '<p>Comfortable and breathable organic cotton t-shirt perfect for everyday wear. Available in multiple sizes and colors.</p>',
                'price' => 24.99,
                'sale_price' => 19.99,
                'cost' => 8.00,
                'stock_quantity' => 200,
                'low_stock_threshold' => 20,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => false,
                'category_id' => $categories->where('slug', 'clothing')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Modern JavaScript Development',
                'description' => 'Complete guide to modern JavaScript and TypeScript',
                'content' => '<p>Learn the latest JavaScript features, TypeScript, React, Node.js, and best practices for modern web development.</p>',
                'price' => 49.99,
                'cost' => 15.00,
                'stock_quantity' => 100,
                'low_stock_threshold' => 15,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => false,
                'category_id' => $categories->where('slug', 'books')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Yoga Mat - Premium Quality',
                'description' => 'Non-slip yoga mat with carrying strap',
                'content' => '<p>6mm thick eco-friendly yoga mat with excellent grip and cushioning. Perfect for yoga, pilates, and floor exercises.</p>',
                'price' => 39.99,
                'cost' => 15.00,
                'stock_quantity' => 75,
                'low_stock_threshold' => 10,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => false,
                'category_id' => $categories->where('slug', 'sports-outdoors')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'LED Desk Lamp',
                'description' => 'Adjustable LED desk lamp with USB charging port',
                'content' => '<p>Energy-efficient LED desk lamp with adjustable brightness levels, color temperatures, and built-in USB charging port.</p>',
                'price' => 34.99,
                'cost' => 12.00,
                'stock_quantity' => 45,
                'low_stock_threshold' => 8,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => false,
                'category_id' => $categories->where('slug', 'home-garden')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Mechanical Keyboard - RGB',
                'description' => 'Gaming mechanical keyboard with RGB backlight',
                'content' => '<p>Professional gaming keyboard with mechanical switches, customizable RGB lighting, and programmable keys.</p>',
                'price' => 119.99,
                'sale_price' => 99.99,
                'cost' => 50.00,
                'stock_quantity' => 25,
                'low_stock_threshold' => 5,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => true,
                'category_id' => $categories->where('slug', 'electronics')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Leather Wallet - Minimalist',
                'description' => 'Slim genuine leather wallet with RFID protection',
                'content' => '<p>Handcrafted genuine leather wallet with RFID blocking technology. Holds 6-8 cards and cash in a slim design.</p>',
                'price' => 44.99,
                'cost' => 15.00,
                'stock_quantity' => 60,
                'low_stock_threshold' => 10,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => false,
                'category_id' => $categories->where('slug', 'clothing')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Camping Tent - 4 Person',
                'description' => 'Waterproof camping tent for 4 people',
                'content' => '<p>Durable waterproof tent with easy setup, ventilation system, and spacious interior for comfortable camping.</p>',
                'price' => 159.99,
                'cost' => 70.00,
                'stock_quantity' => 15,
                'low_stock_threshold' => 3,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => false,
                'category_id' => $categories->where('slug', 'sports-outdoors')->first()?->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Coffee Maker - Programmable',
                'description' => '12-cup programmable coffee maker',
                'content' => '<p>Brew perfect coffee every morning with programmable settings, auto-shutoff, and keep-warm function.</p>',
                'price' => 79.99,
                'cost' => 30.00,
                'stock_quantity' => 40,
                'low_stock_threshold' => 8,
                'track_inventory' => true,
                'status' => 'active',
                'is_featured' => false,
                'category_id' => $categories->where('slug', 'home-garden')->first()?->id,
                'user_id' => $user->id,
            ],
        ];

        foreach ($products as $productData) {
            // Use slug for uniqueness
            $slug = \Str::slug($productData['name']);
            $product = Product::updateOrCreate(
                ['slug' => $slug],
                $productData
            );

            // Attach random tags to each product
            $randomTags = $tags->random(rand(2, 4))->pluck('id')->toArray();
            $product->tags()->sync($randomTags);
        }

        $this->command->info('Successfully seeded '.count($products).' products with tags');
    }
}
