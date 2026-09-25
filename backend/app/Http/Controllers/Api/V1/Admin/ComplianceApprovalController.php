<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreComplianceApprovalRequest;
use App\Http\Resources\ComplianceApprovalResource;
use App\Models\ComplianceApproval;
use App\Models\SiteSetting;
use App\Services\LegalDocumentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ComplianceApprovalController extends Controller
{
    public function __construct(private readonly LegalDocumentManagementService $management) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SiteSetting::class);

        return ComplianceApprovalResource::collection(ComplianceApproval::query()->orderByDesc('id')->get());
    }

    public function store(StoreComplianceApprovalRequest $request): JsonResponse
    {
        Gate::authorize('recordApproval', SiteSetting::class);

        return (new ComplianceApprovalResource($this->management->recordApproval($this->authenticatedAdmin($request), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
