<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Database\QueryException;

class GuestCartService
{
    public function resolve(?string $token): Cart
    {
        if ($token !== null) {
            return Cart::query()->where('token', $token)->firstOrFail();
        }

        return $this->create();
    }

    private function create(): Cart
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return Cart::query()->create([
                    'token' => bin2hex(random_bytes(32)),
                ]);
            } catch (QueryException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Guest cart token generation did not complete.');
    }
}
