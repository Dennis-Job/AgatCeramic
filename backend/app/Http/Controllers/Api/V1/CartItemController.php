<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CartTokenRequest;
use App\Http\Requests\Api\V1\StoreCartItemRequest;
use App\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Services\CartItemManagementService;
use App\Services\GuestCartService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CartItemController extends Controller
{
    public function __construct(
        private readonly CartItemManagementService $managementService,
        private readonly GuestCartService $guestCartService,
    ) {}

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $result = $this->managementService->add(
            $this->guestCartService->resolve($request->cartToken()),
            $request->integer('product_id'),
            $request->integer('quantity'),
        );

        return (new CartItemResource($result['item']))->response()->setStatusCode(
            $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    public function update(UpdateCartItemRequest $request, CartItem $item): CartItemResource
    {
        return new CartItemResource($this->managementService->update(
            $this->guestCartService->resolve($request->cartToken()),
            $item,
            $request->integer('quantity'),
        ));
    }

    public function destroy(CartTokenRequest $request, CartItem $item): Response
    {
        $this->managementService->delete($this->guestCartService->resolve($request->cartToken()), $item);

        return response()->noContent();
    }
}
