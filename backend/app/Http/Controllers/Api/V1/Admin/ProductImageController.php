<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductImageRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductImageRequest;
use App\Http\Resources\Catalog\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProductImageController extends Controller
{
    public function __construct(private readonly ProductImageManagementService $managementService) {}

    public function index(Product $product): AnonymousResourceCollection
    {
        Gate::authorize('view', $product);

        return ProductImageResource::collection($product->images()->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->paginate(100));
    }

    public function store(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);
        $image = $this->managementService->create($request->user(), $product, $request->file('image'), $request->safe()->except('image'));

        return (new ProductImageResource($image))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateProductImageRequest $request, Product $product, ProductImage $image): ProductImageResource
    {
        Gate::authorize('update', $product);
        $this->ensureBelongsToProduct($product, $image);

        return new ProductImageResource($this->managementService->update($request->user(), $product, $image, $request->validated()));
    }

    public function destroy(Product $product, ProductImage $image): Response
    {
        Gate::authorize('delete', $product);
        $this->ensureBelongsToProduct($product, $image);
        $this->managementService->delete(request()->user(), $product, $image);

        return response()->noContent();
    }

    private function ensureBelongsToProduct(Product $product, ProductImage $image): void
    {
        abort_unless($image->product_id === $product->id, Response::HTTP_NOT_FOUND);
    }
}
