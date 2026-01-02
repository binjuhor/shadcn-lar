<?php

namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Finance\Models\Category;

class DefaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Salary', 'type' => 'income', 'icon' => 'wallet', 'color' => '#10b981'],
            ['name' => 'Business Income', 'type' => 'income', 'icon' => 'briefcase', 'color' => '#3b82f6'],
            ['name' => 'Investment Income', 'type' => 'income', 'icon' => 'trending-up', 'color' => '#8b5cf6'],
            ['name' => 'Other Income', 'type' => 'income', 'icon' => 'plus-circle', 'color' => '#6b7280'],

            ['name' => 'Food & Dining', 'type' => 'expense', 'icon' => 'utensils', 'color' => '#ef4444'],
            ['name' => 'Transportation', 'type' => 'expense', 'icon' => 'car', 'color' => '#f59e0b'],
            ['name' => 'Housing', 'type' => 'expense', 'icon' => 'home', 'color' => '#14b8a6'],
            ['name' => 'Utilities', 'type' => 'expense', 'icon' => 'zap', 'color' => '#fbbf24'],
            ['name' => 'Healthcare', 'type' => 'expense', 'icon' => 'heart', 'color' => '#ec4899'],
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'film', 'color' => '#8b5cf6'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => 'shopping-bag', 'color' => '#f43f5e'],
            ['name' => 'Education', 'type' => 'expense', 'icon' => 'book', 'color' => '#0ea5e9'],
            ['name' => 'Insurance', 'type' => 'expense', 'icon' => 'shield', 'color' => '#06b6d4'],
            ['name' => 'Gifts & Donations', 'type' => 'expense', 'icon' => 'more-horizontal', 'color' => '#6b7280'],
            ['name' => 'Other Expenses', 'type' => 'expense', 'icon' => 'more-horizontal', 'color' => '#6b7280'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name'], 'user_id' => null],
                $category
            );
        }
    }
}
