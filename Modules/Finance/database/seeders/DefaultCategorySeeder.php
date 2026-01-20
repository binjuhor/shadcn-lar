<?php

namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Finance\Models\Category;

class DefaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Income categories
            ['name' => 'Salary', 'type' => 'income', 'icon' => 'wallet', 'color' => '#10b981', 'is_passive' => false],
            ['name' => 'Business Income', 'type' => 'income', 'icon' => 'briefcase', 'color' => '#3b82f6', 'is_passive' => false],
            ['name' => 'Affiliate Income', 'type' => 'income', 'icon' => 'briefcase', 'color' => '#22c55e', 'is_passive' => true],
            ['name' => 'Investment Income', 'type' => 'income', 'icon' => 'trending-up', 'color' => '#8b5cf6', 'is_passive' => true],
            ['name' => 'Other Income', 'type' => 'income', 'icon' => 'plus-circle', 'color' => '#6b7280', 'is_passive' => false],

            // Expense categories - Essential (needs)
            ['name' => 'Food & Dining', 'type' => 'expense', 'icon' => 'utensils', 'color' => '#ef4444', 'expense_type' => 'essential'],
            ['name' => 'Transportation', 'type' => 'expense', 'icon' => 'car', 'color' => '#f59e0b', 'expense_type' => 'essential'],
            ['name' => 'Housing', 'type' => 'expense', 'icon' => 'home', 'color' => '#14b8a6', 'expense_type' => 'essential'],
            ['name' => 'Utilities', 'type' => 'expense', 'icon' => 'zap', 'color' => '#fbbf24', 'expense_type' => 'essential'],
            ['name' => 'Healthcare', 'type' => 'expense', 'icon' => 'heart', 'color' => '#ec4899', 'expense_type' => 'essential'],
            ['name' => 'Insurance', 'type' => 'expense', 'icon' => 'shield', 'color' => '#06b6d4', 'expense_type' => 'essential'],

            // Expense categories - Discretionary (wants)
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'film', 'color' => '#8b5cf6', 'expense_type' => 'discretionary'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => 'shopping-bag', 'color' => '#f43f5e', 'expense_type' => 'discretionary'],
            ['name' => 'Gifts & Donations', 'type' => 'expense', 'icon' => 'more-horizontal', 'color' => '#6b7280', 'expense_type' => 'discretionary'],

            // Expense categories - Other
            ['name' => 'Education', 'type' => 'expense', 'icon' => 'book', 'color' => '#0ea5e9', 'expense_type' => null],
            ['name' => 'Other Expenses', 'type' => 'expense', 'icon' => 'more-horizontal', 'color' => '#6b7280', 'expense_type' => null],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name'], 'user_id' => null],
                $category
            );
        }
    }
}
