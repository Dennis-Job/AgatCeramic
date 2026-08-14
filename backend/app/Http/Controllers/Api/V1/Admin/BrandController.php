<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreBrandRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBrandRequest;
use App\Http\Resources\Catalog\BrandResource;
use App\Models\Brand;
use App\Services\BrandManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BrandController extends Controller
{
    public function __construct(private readonly BrandManagementService $managementService) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Brand::class);

        return BrandResource::collection(Brand::query()->orderBy('name')->paginate(25));
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        Gate::authorize('create', Brand::class);

        return (new BrandResource($this->managementService->create($request->user(), $request->validated())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Brand $brand): BrandResource
    {
        Gate::authorize('view', $brand);

        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        Gate::authorize('update', $brand);

        return new BrandResource($this->managementService->update($request->user(), $brand, $request->validated()));
    }

    public function destroy(Brand $brand): Response
    {
        Gate::authorize('delete', $brand);
        $this->managementService->delete(request()->user(), $brand);

        return response()->noContent();
    }
}
