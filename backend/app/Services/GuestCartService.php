<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Database\QueryException;

class GuestCartService
{
    public function __construct(
        private readonly CartTokenHasher $tokenHasher,
        private readonly GuestCartLifetime $lifetime,
    ) {}

    public function resolve(?string $token): ResolvedGuestCart
    {
        if ($token !== null) {
            $cart = Cart::query()
                ->where('token_hash', $this->tokenHasher->hash($token))
                ->where('expires_at', '>', now())
                ->firstOrFail();

            return new ResolvedGuestCart($cart, $token);
        }

        return $this->create();
    }

    private function create(): ResolvedGuestCart
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $token = bin2hex(random_bytes(32));

            try {
                $cart = Cart::query()->create([
                    'token_hash' => $this->tokenHasher->hash($token),
                    'expires_at' => $this->lifetime->emptyExpiry(),
                ]);

                return new ResolvedGuestCart($cart, $token);
            } catch (QueryException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Guest cart token generation did not complete.');
    }
}
