<?php

namespace App\Services\Retention;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderDataDestructionService
{
    public function __construct(private readonly RetentionPolicy $policy) {}

    public function anonymize(Order $order, CarbonImmutable $anchor): void
    {
        $orderId = (int) $order->id;
        $order->update([
            'order_number' => 'ANON-'.strtoupper(bin2hex(random_bytes(8))),
            'customer_name' => null,
            'customer_phone' => null,
            'customer_email' => null,
            'delivery_address' => null,
            'customer_comment' => null,
            'payment_reference' => null,
            'anonymized_at' => now(),
            'commercial_retention_until' => $this->policy->commercialRetentionUntil($anchor),
        ]);
        $order->comments()->delete();
        $order->statusHistory()->update(['actor_id' => null, 'actor_snapshot' => null]);

        if (DB::table('order_comments')->where('order_id', $orderId)->exists()) {
            throw new RuntimeException('Order child reconciliation failed.');
        }
    }

    public function delete(Order $order): void
    {
        $orderId = (int) $order->id;
        $order->delete();

        foreach (['order_items', 'order_comments', 'order_status_histories'] as $table) {
            if (DB::table($table)->where('order_id', $orderId)->exists()) {
                throw new RuntimeException('Order child reconciliation failed.');
            }
        }
    }
}
