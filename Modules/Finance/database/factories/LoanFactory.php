<?php

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Loan;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'borrower_name' => fake()->name(),
            'amount' => fake()->numberBetween(100000, 10000000),
            'amount_repaid' => 0,
            'currency_code' => 'VND',
            'loan_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'due_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'message' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
