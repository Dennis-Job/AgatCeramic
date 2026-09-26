<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreBannerRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\BannerManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BannerController extends Controller
{
    public function __construct(private readonly BannerManagementService $managementService) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Banner::class);

        return BannerResource::collection(Banner::query()->orderByDesc('updated_at')->orderByDesc('id')->paginate(25));
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        Gate::authorize('create', Banner::class);

        return (new BannerResource($this->managementService->create($this->authenticatedAdmin($request), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Banner $banner): BannerResource
    {
        Gate::authorize('view', $banner);

        return new BannerResource($banner);
    }

    public function update(UpdateBannerRequest $request, Banner $banner): BannerResource
    {
        Gate::authorize('update', $banner);

        return new BannerResource($this->managementService->update($this->authenticatedAdmin($request), $banner, $request->validated()));
    }

    public function destroy(Request $request, Banner $banner): Response
    {
        Gate::authorize('delete', $banner);
        $this->managementService->delete($this->authenticatedAdmin($request), $banner);

        return response()->noContent();
    }
}
