<?php

namespace Tests\Concerns;

use App\Services\GuestCartService;
use App\Services\ResolvedGuestCart;

trait CreatesGuestCarts
{
    protected function createGuestCart(): ResolvedGuestCart
    {
        return app(GuestCartService::class)->resolve(null);
    }
}
