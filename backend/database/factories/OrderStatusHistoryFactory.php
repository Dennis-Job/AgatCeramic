<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderStatusHistory> */
class OrderStatusHistoryFactory extends Factory
{
    protected $model = OrderStatusHistory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => 'new',
            'to_status' => 'processing',
            'actor_id' => User::factory(),
            'actor_snapshot' => ['name' => fake()->name()],
            'occurred_at' => now(),
        ];
    }
}
