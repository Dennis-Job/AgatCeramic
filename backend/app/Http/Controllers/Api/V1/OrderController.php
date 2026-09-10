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
        $result = $this->orderCreationService->create(
            $this->guestCartService->resolve($request->cartToken()),
            $request->orderAttributes(),
            $request->idempotencyKey(),
        );

        return (new OrderResource($result->order))->response()
            ->setStatusCode($result->replayed ? Response::HTTP_OK : Response::HTTP_CREATED)
            ->header('Idempotent-Replayed', $result->replayed ? 'true' : 'false');
    }
}
