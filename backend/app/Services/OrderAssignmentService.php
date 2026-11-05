<?php

namespace App\Services;

use App\Enums\AdminUserStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderAssignmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PermissionChecker $permissionChecker,
    ) {}

    public function assign(User $actor, Order $order, ?int $assigneeId): Order
    {
        return DB::transaction(function () use ($actor, $order, $assigneeId): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->assignee_id === $assigneeId) {
                return $order;
            }

            if ($assigneeId !== null) {
                $assignee = User::query()->whereKey($assigneeId)->where('status', AdminUserStatus::Active->value)->first();

                if ($assignee === null || ! $this->permissionChecker->allows($assignee, 'orders.manage')) {
                    throw ValidationException::withMessages([
                        'assignee_id' => ['Ответственным можно назначить только активного сотрудника с правом управления заказами.'],
                    ]);
                }
            }

            $previousAssigneeId = $order->assignee_id;
            $order->update([
                'assignee_id' => $assigneeId,
                'assigned_at' => $assigneeId === null ? null : now(),
            ]);
            $this->auditLogService->record($actor, 'order.assignee-changed', $order, [
                'from_assignee_id' => $previousAssigneeId,
                'to_assignee_id' => $assigneeId,
            ]);

            return $order;
        });
    }
}
