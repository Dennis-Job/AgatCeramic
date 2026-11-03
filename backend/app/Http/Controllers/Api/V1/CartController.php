<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveCartRequest;
use App\Http\Resources\CartResource;
use App\Services\CartItemManagementService;
use App\Services\GuestCartService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly GuestCartService $guestCartService,
        private readonly CartItemManagementService $managementService,
    ) {}

    public function show(ResolveCartRequest $request): JsonResponse
    {
        $resolved = $this->guestCartService->resolve($request->cartToken());

        return (new CartResource(
            $resolved->cart,
            $resolved->token,
            $this->managementService->items($resolved->cart),
        ))->response()->setStatusCode(Response::HTTP_OK);
    }
}
