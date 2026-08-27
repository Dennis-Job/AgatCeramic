<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductGroupRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductGroupRequest;
use App\Http\Resources\Catalog\ProductGroupResource;
use App\Models\ProductGroup;
use App\Services\ProductGroupManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProductGroupController extends Controller
{
    public function __construct(private readonly ProductGroupManagementService $service) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProductGroup::class);

        return ProductGroupResource::collection(ProductGroup::query()->with(['axes', 'products.category', 'products.brand', 'products.primaryImage', 'products.attributeValues.attribute.options'])->orderBy('name')->paginate(25));
    }

    public function store(StoreProductGroupRequest $request): JsonResponse
    {
        Gate::authorize('create', ProductGroup::class);

        return (new ProductGroupResource($this->service->create($request->user(), $request->validated())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ProductGroup $productGroup): ProductGroupResource
    {
        Gate::authorize('view', $productGroup);

        return new ProductGroupResource($this->service->load($productGroup));
    }

    public function update(UpdateProductGroupRequest $request, ProductGroup $productGroup): ProductGroupResource
    {
        Gate::authorize('update', $productGroup);

        return new ProductGroupResource($this->service->update($request->user(), $productGroup, $request->validated()));
    }

    public function destroy(ProductGroup $productGroup): Response
    {
        Gate::authorize('delete', $productGroup);
        $this->service->delete(request()->user(), $productGroup);

        return response()->noContent();
    }
}
