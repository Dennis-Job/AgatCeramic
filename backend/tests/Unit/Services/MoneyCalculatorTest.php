<?php

namespace Tests\Unit\Services;

use App\Services\MoneyCalculator;
use PHPUnit\Framework\TestCase;

class MoneyCalculatorTest extends TestCase
{
    public function test_formats_amounts_with_two_decimal_places_without_losing_cents(): void
    {
        $calculator = new MoneyCalculator;

        $this->assertSame('0.00', $calculator->sum([]));
        $this->assertSame('0.00', $calculator->sum(['0.00', '0']));
        $this->assertSame('1.20', $calculator->normalize('00001.2'));
        $this->assertSame('0.03', $calculator->multiply('0.01', 3));
    }

    public function test_sums_stay_exact_above_the_native_integer_limit_in_cents(): void
    {
        $calculator = new MoneyCalculator;

        $this->assertSame('999999999999999999.99', $calculator->sum(['999999999999999999.98', '0.01']));
        $this->assertSame('1000000000000000000.00', $calculator->sum(['999999999999999999.99', '0.01']));
    }
}
