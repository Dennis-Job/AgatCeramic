<?php

namespace App\Services;

class OrderAmountCalculator
{
    public function multiply(string $amount, int $quantity): string
    {
        return $this->format($this->minorUnits($amount) * $quantity);
    }

    /** @param list<string> $amounts */
    public function sum(array $amounts): string
    {
        return $this->format(array_sum(array_map($this->minorUnits(...), $amounts)));
    }

    private function minorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function format(int $minorUnits): string
    {
        return sprintf('%d.%02d', intdiv($minorUnits, 100), $minorUnits % 100);
    }
}
