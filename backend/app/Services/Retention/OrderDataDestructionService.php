<?php

namespace App\Services\Retention;

use App\Models\Customer;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderDataDestructionService
{
    public function __construct(private readonly RetentionPolicy $policy) {}

    public function anonymize(Order $order, CarbonImmutable $anchor): void
    {
        DB::transaction(function () use ($order, $anchor): void {
            $customer = $this->lockCustomer($order);
            $orderId = (int) $order->id;
            $order->update([
                'order_number' => 'ANON-'.strtoupper(bin2hex(random_bytes(8))),
                'customer_id' => null,
                'customer_order_sequence' => null,
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
            $this->forgetUnusedPhone($customer);

            if (DB::table('order_comments')->where('order_id', $orderId)->exists()) {
                throw new RuntimeException('Order child reconciliation failed.');
            }
        });
    }

    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $customer = $this->lockCustomer($order);
            $orderId = (int) $order->id;
            $order->delete();
            $this->forgetUnusedPhone($customer);

            foreach (['order_items', 'order_comments', 'order_status_histories'] as $table) {
                if (DB::table($table)->where('order_id', $orderId)->exists()) {
                    throw new RuntimeException('Order child reconciliation failed.');
                }
            }
        });
    }

    private function lockCustomer(Order $order): ?Customer
    {
        return $order->customer_id === null ? null : Customer::query()->whereKey($order->customer_id)->lockForUpdate()->firstOrFail();
    }

    private function forgetUnusedPhone(?Customer $customer): void
    {
        // Checkout holds the same row lock until its new order is committed.
        if ($customer !== null && ! $customer->orders()->exists()) {
            $customer->update(['phone' => null]);
        }
    }
}
