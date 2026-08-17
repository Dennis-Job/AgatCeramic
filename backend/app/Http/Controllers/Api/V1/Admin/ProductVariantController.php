<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductVariantRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductVariantRequest;
use App\Http\Resources\Catalog\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductVariantManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProductVariantController extends Controller
{
    public function __construct(private readonly ProductVariantManagementService $managementService) {}

    public function index(Product $product): AnonymousResourceCollection
    {
        Gate::authorize('view', $product);

        return ProductVariantResource::collection($product->variants()->with('attributeValues.attribute.options')->orderBy('sort_order')->orderBy('name')->paginate(100));
    }

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);
        $variant = $this->managementService->create($request->user(), $product, $request->validated());

        return (new ProductVariantResource($variant))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product, ProductVariant $variant): ProductVariantResource
    {
        Gate::authorize('view', $product);
        $this->ensureBelongsToProduct($product, $variant);

        return new ProductVariantResource($variant->load('attributeValues.attribute.options'));
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): ProductVariantResource
    {
        Gate::authorize('update', $product);
        $this->ensureBelongsToProduct($product, $variant);

        return new ProductVariantResource($this->managementService->update($request->user(), $variant, $request->validated()));
    }

    public function destroy(Product $product, ProductVariant $variant): Response
    {
        Gate::authorize('delete', $product);
        $this->ensureBelongsToProduct($product, $variant);
        $this->managementService->delete(request()->user(), $variant);

        return response()->noContent();
    }

    private function ensureBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        abort_unless($variant->product_id === $product->id, Response::HTTP_NOT_FOUND);
    }
}
