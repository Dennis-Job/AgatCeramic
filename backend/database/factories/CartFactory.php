<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Services\CartTokenHasher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        return [
            'token_hash' => (new CartTokenHasher)->hash(bin2hex(random_bytes(32))),
            'expires_at' => now()->addDay(),
            'checked_out_at' => null,
        ];
    }
}
