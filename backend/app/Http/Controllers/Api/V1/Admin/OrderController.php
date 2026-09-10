<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListOrderCommentsRequest;
use App\Http\Requests\Api\V1\Admin\ListOrderStatusHistoryRequest;
use App\Http\Requests\Api\V1\Admin\StoreOrderCommentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateOrderPaymentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderCommentResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Http\Resources\OrderStatusResource;
use App\Http\Resources\PaymentRegistrationResource;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\OrderCommentService;
use App\Services\OrderPaymentManagementService;
use App\Services\OrderStatusManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderCommentService $commentService,
        private readonly OrderPaymentManagementService $paymentManagementService,
        private readonly OrderStatusManagementService $statusManagementService,
    ) {}

    public function statuses(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        return OrderStatusResource::collection(OrderStatus::query()->where('is_active', true)->orderBy('sort_order')->get());
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        Gate::authorize('update', $order);

        return new OrderResource($this->statusManagementService->update($this->authenticatedAdmin($request), $order, $request->string('status')->toString()));
    }

    public function statusHistory(ListOrderStatusHistoryRequest $request, Order $order): AnonymousResourceCollection
    {
        Gate::authorize('view', $order);

        return OrderStatusHistoryResource::collection(
            $order->statusHistory()
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->paginate($request->integer('per_page', 25))
                ->withQueryString(),
        );
    }

    public function comments(ListOrderCommentsRequest $request, Order $order): AnonymousResourceCollection
    {
        Gate::authorize('view', $order);

        return OrderCommentResource::collection(
            $order->comments()
                ->orderBy('created_at')
                ->orderBy('id')
                ->paginate($request->integer('per_page', 25))
                ->withQueryString(),
        );
    }

    public function storeComment(StoreOrderCommentRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('createComment', $order);

        return (new OrderCommentResource($this->commentService->add(
            $this->authenticatedAdmin($request),
            $order,
            $request->string('body')->toString(),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updatePayment(UpdateOrderPaymentRequest $request, Order $order): PaymentRegistrationResource
    {
        Gate::authorize('managePayment', $order);

        return new PaymentRegistrationResource($this->paymentManagementService->register(
            $this->authenticatedAdmin($request),
            $order,
            $request->enum('payment_status', PaymentStatus::class) ?? throw new \LogicException('Validated payment status is missing.'),
            $request->validated(),
        ));
    }
}
