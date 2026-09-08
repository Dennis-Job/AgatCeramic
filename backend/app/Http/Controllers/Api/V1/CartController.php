<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveCartRequest;
use App\Http\Resources\CartResource;
use App\Services\GuestCartService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
    public function __construct(private readonly GuestCartService $guestCartService) {}

    public function show(ResolveCartRequest $request): JsonResponse
    {
        $cart = $this->guestCartService->resolve($request->validated('cart_token'));

        return (new CartResource($cart->load('items.product.primaryImage')))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
