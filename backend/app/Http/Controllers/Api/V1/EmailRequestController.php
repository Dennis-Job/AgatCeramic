<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmailRequest;
use App\Services\EmailRequestService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmailRequestController extends Controller
{
    public function __construct(private readonly EmailRequestService $emailRequestService) {}

    public function store(StoreEmailRequest $request): Response
    {
        $this->emailRequestService->create($request->payload());

        return response()->noContent(HttpResponse::HTTP_CREATED);
    }
}
