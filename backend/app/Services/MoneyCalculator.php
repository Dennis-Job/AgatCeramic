<?php

namespace App\Services;

use Brick\Math\BigDecimal;

final class MoneyCalculator
{
    public function normalize(string $amount): string
    {
        return (string) BigDecimal::of($amount)->toScale(2);
    }

    public function multiply(string $amount, int $quantity): string
    {
        return (string) BigDecimal::of($amount)->multipliedBy($quantity)->toScale(2);
    }

    /** @param list<string> $amounts */
    public function sum(array $amounts): string
    {
        $total = BigDecimal::zero();
        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return (string) $total->toScale(2);
    }

    public function compare(string $left, string $right): int
    {
        return BigDecimal::of($left)->compareTo($right);
    }
}
