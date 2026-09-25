<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreLegalDocumentVersionRequest;
use App\Http\Resources\LegalDocumentVersionResource;
use App\Models\LegalDocumentVersion;
use App\Models\SiteSetting;
use App\Services\LegalDocumentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LegalDocumentController extends Controller
{
    public function __construct(private readonly LegalDocumentManagementService $management) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SiteSetting::class);

        return LegalDocumentVersionResource::collection(LegalDocumentVersion::query()->orderByDesc('id')->get());
    }

    public function store(StoreLegalDocumentVersionRequest $request): JsonResponse
    {
        Gate::authorize('update', SiteSetting::class);

        return (new LegalDocumentVersionResource($this->management->create($this->authenticatedAdmin($request), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function publish(Request $request, LegalDocumentVersion $legalDocumentVersion): LegalDocumentVersionResource
    {
        Gate::authorize('update', SiteSetting::class);

        return new LegalDocumentVersionResource($this->management->publish($this->authenticatedAdmin($request), $legalDocumentVersion));
    }
}
