<?php

namespace Database\Factories;

use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderStatus> */
class OrderStatusFactory extends Factory
{
    protected $model = OrderStatus::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => fake()->unique()->numberBetween(1000, 65000),
            'is_active' => true,
            'is_terminal' => false,
            'sets_completed_at' => false,
        ];
    }
}
