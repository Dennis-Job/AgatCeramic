<?php

namespace App\Services;

use App\Models\Cart;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class GuestCartLifetime
{
    public function markEmpty(Cart $cart, ?CarbonInterface $from = null): void
    {
        $cart->forceFill([
            'checked_out_at' => null,
            'expires_at' => $this->moment($from)->addHours($this->positiveConfig('cart.empty_ttl_hours')),
        ])->save();
    }

    public function markActive(Cart $cart, ?CarbonInterface $from = null): void
    {
        $cart->forceFill([
            'checked_out_at' => null,
            'expires_at' => $this->moment($from)->addDays($this->positiveConfig('cart.abandoned_ttl_days')),
        ])->save();
    }

    public function markCheckedOut(Cart $cart, ?CarbonInterface $from = null): void
    {
        $moment = $this->moment($from);
        $cart->forceFill([
            'checked_out_at' => $moment,
            'expires_at' => $moment->addHours($this->positiveConfig('cart.checked_out_ttl_hours')),
        ])->save();
    }

    public function emptyExpiry(?CarbonInterface $from = null): CarbonImmutable
    {
        return $this->moment($from)->addHours($this->positiveConfig('cart.empty_ttl_hours'));
    }

    private function moment(?CarbonInterface $from): CarbonImmutable
    {
        return $from === null ? CarbonImmutable::now() : CarbonImmutable::instance($from);
    }

    private function positiveConfig(string $key): int
    {
        $value = filter_var(config($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new \LogicException("{$key} must be a positive integer.");
        }

        return $value;
    }
}
