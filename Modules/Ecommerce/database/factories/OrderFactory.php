<?php

namespace Modules\Ecommerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ecommerce\Models\Order;
use App\Models\User;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 20, 500);
        $tax = $subtotal * 0.1;
        $shipping = $this->faker->randomFloat(2, 0, 20);
        $discount = $this->faker->boolean(30) ? $subtotal * $this->faker->randomFloat(2, 0.05, 0.2) : 0;
        $total = $subtotal + $tax + $shipping - $discount;

        return [
            'order_number' => 'ORD-' . strtoupper($this->faker->unique()->bothify('??######')),
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'cancelled']),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'discount' => $discount,
            'total' => $total,
            'currency' => 'USD',
            'payment_method' => $this->faker->randomElement(['credit_card', 'paypal', 'bank_transfer']),
            'payment_status' => $this->faker->randomElement(['unpaid', 'paid', 'refunded']),
            'paid_at' => $this->faker->boolean(60) ? $this->faker->dateTimeBetween('-3 months', 'now') : null,
            'customer_notes' => $this->faker->optional()->sentence(),
            'admin_notes' => $this->faker->optional()->sentence(),
            'billing_address' => $this->generateAddress(),
            'shipping_address' => $this->generateAddress(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'paid_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'payment_status' => 'paid',
            'paid_at' => $this->faker->dateTimeBetween('-3 months', '-1 day'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    private function generateAddress(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'company' => $this->faker->optional()->company(),
            'address_1' => $this->faker->streetAddress(),
            'address_2' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postcode' => $this->faker->postcode(),
            'country' => $this->faker->countryCode(),
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
        ];
    }
}
