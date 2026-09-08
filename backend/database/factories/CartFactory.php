<?php

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /** @return array<string, string> */
    public function definition(): array
    {
        return [
            'token' => bin2hex(random_bytes(32)),
        ];
    }
}
