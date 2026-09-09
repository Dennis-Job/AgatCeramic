<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCallbackRequest;
use App\Services\CallbackRequestService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CallbackRequestController extends Controller
{
    public function __construct(private readonly CallbackRequestService $callbackRequestService) {}

    public function store(StoreCallbackRequest $request): Response
    {
        $this->callbackRequestService->create($request->safe()->only(['name', 'phone']));

        return response()->noContent(HttpResponse::HTTP_CREATED);
    }
}
