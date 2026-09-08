<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function update(User $actor, Order $order, string $targetCode): Order
    {
        return DB::transaction(function () use ($actor, $order, $targetCode): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $currentStatus = OrderStatus::query()->where('code', $order->status)->lockForUpdate()->first();
            $targetStatus = OrderStatus::query()
                ->where('code', $targetCode)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($targetStatus === null) {
                throw ValidationException::withMessages([
                    'status' => ['Выбранный статус недоступен.'],
                ]);
            }

            if ($currentStatus?->is_terminal && $order->status !== $targetStatus->code) {
                throw ValidationException::withMessages([
                    'status' => ['Терминальный статус заказа изменить нельзя.'],
                ]);
            }

            if ($order->status === $targetStatus->code) {
                return $order;
            }

            $previousStatus = $order->status;
            $order->update([
                'status' => $targetStatus->code,
                'completed_at' => $targetStatus->sets_completed_at ? now() : null,
            ]);
            $this->auditLogService->record($actor, 'order.status-changed', $order, [
                'from_status' => $previousStatus,
                'to_status' => $targetStatus->code,
            ]);

            return $order;
        });
    }
}
