<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\GuestCartService;
use App\Services\OrderCreationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly GuestCartService $guestCartService,
        private readonly OrderCreationService $orderCreationService,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderCreationService->create(
            $this->guestCartService->resolve($request->cartToken()),
            $request->safe()->except('cart_token', 'website'),
        );

        return (new OrderResource($order))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
