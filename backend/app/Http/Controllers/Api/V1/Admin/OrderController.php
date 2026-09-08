<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusResource;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\OrderStatusManagementService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private readonly OrderStatusManagementService $statusManagementService) {}

    public function statuses(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        return OrderStatusResource::collection(OrderStatus::query()->where('is_active', true)->orderBy('sort_order')->get());
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        Gate::authorize('update', $order);

        return new OrderResource($this->statusManagementService->update($request->user(), $order, $request->validated('status')));
    }
}
