<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePartnerRequest;
use App\Services\PartnerRequestService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PartnerRequestController extends Controller
{
    public function __construct(private readonly PartnerRequestService $partnerRequestService) {}

    public function store(StorePartnerRequest $request): Response
    {
        $this->partnerRequestService->create($request->safe()->only(['name', 'phone', 'email', 'message']));

        return response()->noContent(HttpResponse::HTTP_CREATED);
    }
}
