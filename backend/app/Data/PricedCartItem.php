<?php

namespace App\Data;

use App\Models\CartItem;

final readonly class PricedCartItem
{
    public function __construct(
        public CartItem $item,
        public ?string $unitPrice,
        public ?string $lineTotal,
    ) {}
}
