<?php

namespace App\Support;

final class OrderStatusSelection
{
    public static function allows(string $code): bool
    {
        // Keep legacy codes readable without allowing payment to duplicate order progress.
        return ! in_array($code, ['awaiting_payment', 'paid'], true);
    }
}
