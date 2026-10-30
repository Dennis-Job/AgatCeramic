<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProductCollectionsRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductCollectionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductCollectionRequest;
use App\Http\Resources\Catalog\ProductCollectionResource;
use App\Models\ProductCollection;
use App\Queries\ProductCollectionQuery;
use App\Services\ProductCollectionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProductCollectionController extends Controller
{
    public function __construct(private readonly ProductCollectionManagementService $managementService) {}

    public function index(ListProductCollectionsRequest $request, ProductCollectionQuery $query): AnonymousResourceCollection
    {
        return ProductCollectionResource::collection($query->paginate($request->validated()));
    }

    public function store(StoreProductCollectionRequest $request): JsonResponse
    {
        return (new ProductCollectionResource($this->managementService->create($this->authenticatedAdmin($request), $request->validated())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ProductCollection $collection): ProductCollectionResource
    {
        Gate::authorize('view', $collection);

        return new ProductCollectionResource($collection->load(['brand.logo', 'brand.documents', 'image'])->loadCount('products'));
    }

    public function update(UpdateProductCollectionRequest $request, ProductCollection $collection): ProductCollectionResource
    {
        return new ProductCollectionResource($this->managementService->update($this->authenticatedAdmin($request), $collection, $request->validated()));
    }

    public function destroy(Request $request, ProductCollection $collection): Response
    {
        Gate::authorize('delete', $collection);
        $this->managementService->delete($this->authenticatedAdmin($request), $collection);

        return response()->noContent();
    }
}
