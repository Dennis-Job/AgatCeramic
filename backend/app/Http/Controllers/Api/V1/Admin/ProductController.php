<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProductsRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductRequest;
use App\Http\Resources\Catalog\ProductResource;
use App\Models\Product;
use App\Queries\ProductQuery;
use App\Services\ProductManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductManagementService $managementService,
        private readonly ProductQuery $productQuery,
    ) {}

    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $request->validated();
        $query = $this->productQuery->filtered($filters)
            ->with(['category', 'brand', 'primaryImage', 'groupMembership.group']);

        return ProductResource::collection(
            $query->paginate($filters['per_page'] ?? 25)->withQueryString()
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $product = $this->managementService->create($request->user(), $request->validated());

        return (new ProductResource($product->load(['category', 'brand', 'primaryImage', 'groupMembership.group'])))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(['category', 'brand', 'primaryImage', 'groupMembership.group']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        return new ProductResource($this->managementService->update($request->user(), $product, $request->validated())->load(['category', 'brand', 'primaryImage', 'groupMembership.group']));
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);
        $this->managementService->delete(request()->user(), $product);

        return response()->noContent();
    }
}
