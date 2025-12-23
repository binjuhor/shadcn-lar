<?php

namespace Modules\Invoice\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoice\Models\Invoice;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $invoiceDate = fake()->dateTimeBetween('-3 months', 'now');
        $dueDate = fake()->dateTimeBetween($invoiceDate, '+30 days');
        $status = fake()->randomElement(['draft', 'sent', 'paid', 'overdue', 'cancelled']);

        return [
            'invoice_number' => 'INV-' . fake()->unique()->numerify('########'),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'status' => $status,
            'from_name' => fake()->company(),
            'from_address' => fake()->address(),
            'from_email' => fake()->companyEmail(),
            'from_phone' => fake()->phoneNumber(),
            'to_name' => fake()->name(),
            'to_address' => fake()->address(),
            'to_email' => fake()->email(),
            'subtotal' => 0,
            'tax_rate' => fake()->randomElement([0, 0.05, 0.1, 0.15]),
            'tax_amount' => 0,
            'total' => 0,
            'notes' => fake()->optional(0.3)->sentence(),
            'user_id' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent']);
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid']);
    }

    public function overdue(): static
    {
        return $this->state(['status' => 'overdue']);
    }
}
