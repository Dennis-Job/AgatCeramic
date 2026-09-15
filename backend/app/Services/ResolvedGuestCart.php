<?php

namespace App\Services;

use App\Models\Cart;

final readonly class ResolvedGuestCart
{
    public function __construct(
        public Cart $cart,
        public string $token,
    ) {}
}
