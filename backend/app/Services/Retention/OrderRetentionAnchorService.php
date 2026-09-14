<?php

namespace App\Services\Retention;

use App\Models\Order;
use Carbon\CarbonImmutable;
use RuntimeException;

final class OrderRetentionAnchorService
{
    /** @param list<string> $terminalCodes */
    public function latest(Order $order, array $terminalCodes): ?CarbonImmutable
    {
        $values = [$order->completed_at, $order->paid_at];
        $values[] = $order->statusHistory()
            ->whereIn('to_status', $terminalCodes)
            ->max('occurred_at');
        $latest = null;

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! $value instanceof \DateTimeInterface) {
                throw new RuntimeException('Order retention anchor has an invalid type.');
            }

            $candidate = CarbonImmutable::parse($value);
            if ($latest === null || $candidate->greaterThan($latest)) {
                $latest = $candidate;
            }
        }

        return $latest;
    }
}
