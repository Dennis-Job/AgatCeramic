<?php

namespace App\Services;

final class CartTokenHasher
{
    public function hash(string $token): string
    {
        $key = config('cart.token_hmac_key');
        if (! is_string($key) || $key === '') {
            throw new \LogicException('Guest cart token HMAC key is not configured.');
        }

        return hash_hmac('sha256', $token, $key);
    }
}
