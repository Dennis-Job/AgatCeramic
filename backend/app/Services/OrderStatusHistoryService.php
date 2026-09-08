<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;

class OrderStatusHistoryService
{
    public function record(User $actor, Order $order, string $fromStatus, string $toStatus): OrderStatusHistory
    {
        return OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $actor->id,
            'actor_snapshot' => ['name' => $actor->name],
            'occurred_at' => now(),
        ]);
    }
}
