<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        return [
            'order_number' => 'AC-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(5))),
            'customer_name' => fake()->name(),
            'customer_phone' => '+79991234567',
            'customer_email' => fake()->safeEmail(),
            'delivery_address' => fake()->address(),
            'customer_comment' => null,
            'status' => 'new',
            'payment_status' => PaymentStatus::NotPaid,
            'total_amount' => '1000.00',
        ];
    }
}
