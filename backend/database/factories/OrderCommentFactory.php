<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderComment> */
class OrderCommentFactory extends Factory
{
    protected $model = OrderComment::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'author_id' => User::factory(),
            'author_snapshot' => ['name' => fake()->name()],
            'body' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
