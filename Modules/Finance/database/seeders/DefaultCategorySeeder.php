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
            ['name' => 'Salary', 'name_key' => 'category.salary', 'type' => 'income', 'icon' => 'wallet', 'color' => '#10b981', 'is_passive' => false],
            ['name' => 'Business Income', 'name_key' => 'category.business_income', 'type' => 'income', 'icon' => 'briefcase', 'color' => '#3b82f6', 'is_passive' => false],
            ['name' => 'Affiliate Income', 'name_key' => 'category.affiliate_income', 'type' => 'income', 'icon' => 'briefcase', 'color' => '#22c55e', 'is_passive' => true],
            ['name' => 'Investment Income', 'name_key' => 'category.investment_income', 'type' => 'income', 'icon' => 'trending-up', 'color' => '#8b5cf6', 'is_passive' => true],
            ['name' => 'Other Income', 'name_key' => 'category.other_income', 'type' => 'income', 'icon' => 'plus-circle', 'color' => '#6b7280', 'is_passive' => false],

            // Expense categories - Essential (needs)
            ['name' => 'Food & Dining', 'name_key' => 'category.food_dining', 'type' => 'expense', 'icon' => 'utensils', 'color' => '#ef4444', 'expense_type' => 'essential'],
            ['name' => 'Transportation', 'name_key' => 'category.transportation', 'type' => 'expense', 'icon' => 'car', 'color' => '#f59e0b', 'expense_type' => 'essential'],
            ['name' => 'Housing', 'name_key' => 'category.housing', 'type' => 'expense', 'icon' => 'home', 'color' => '#14b8a6', 'expense_type' => 'essential'],
            ['name' => 'Utilities', 'name_key' => 'category.utilities', 'type' => 'expense', 'icon' => 'zap', 'color' => '#fbbf24', 'expense_type' => 'essential'],
            ['name' => 'Healthcare', 'name_key' => 'category.healthcare', 'type' => 'expense', 'icon' => 'heart', 'color' => '#ec4899', 'expense_type' => 'essential'],
            ['name' => 'Insurance', 'name_key' => 'category.insurance', 'type' => 'expense', 'icon' => 'shield', 'color' => '#06b6d4', 'expense_type' => 'essential'],

            // Expense categories - Discretionary (wants)
            ['name' => 'Entertainment', 'name_key' => 'category.entertainment', 'type' => 'expense', 'icon' => 'film', 'color' => '#8b5cf6', 'expense_type' => 'discretionary'],
            ['name' => 'Shopping', 'name_key' => 'category.shopping', 'type' => 'expense', 'icon' => 'shopping-bag', 'color' => '#f43f5e', 'expense_type' => 'discretionary'],
            ['name' => 'Gifts & Donations', 'name_key' => 'category.gifts_donations', 'type' => 'expense', 'icon' => 'more-horizontal', 'color' => '#6b7280', 'expense_type' => 'discretionary'],

            // Expense categories - Other
            ['name' => 'Education', 'name_key' => 'category.education', 'type' => 'expense', 'icon' => 'book', 'color' => '#0ea5e9', 'expense_type' => null],
            ['name' => 'Other Expenses', 'name_key' => 'category.other_expenses', 'type' => 'expense', 'icon' => 'more-horizontal', 'color' => '#6b7280', 'expense_type' => null],
            ['name' => 'Investment Expenses', 'name_key' => 'category.investment_expenses', 'type' => 'expense', 'icon' => 'more-horizontal', 'color' => '#6b7280', 'expense_type' => null],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name'], 'user_id' => null],
                $category
            );
        }
    }
}
