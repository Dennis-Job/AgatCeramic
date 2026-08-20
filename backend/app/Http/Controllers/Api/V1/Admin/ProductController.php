<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProductsRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductRequest;
use App\Http\Resources\Catalog\ProductResource;
use App\Models\Product;
use App\Services\ProductManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductManagementService $managementService) {}

    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $filters = $request->validated();
        $query = Product::query()->with(['category', 'brand']);

        if ($search = $filters['search'] ?? null) {
            $pattern = '%'.mb_strtolower($search).'%';
            $query->where(function ($query) use ($pattern): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$pattern])
                    ->orWhereHas('variants', fn ($variants) => $variants
                        ->whereRaw('LOWER(sku) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(article_number) LIKE ?', [$pattern])
                        ->orWhere('barcode', 'LIKE', $pattern));
            });
        }

        foreach (['category_id', 'brand_id', 'is_active'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['has_stock'])) {
            $stockQuery = fn ($variants) => $variants->where('stock_quantity', '>', 0);
            $filters['has_stock'] ? $query->whereHas('variants', $stockQuery) : $query->whereDoesntHave('variants', $stockQuery);
        }

        if (isset($filters['price_from']) || isset($filters['price_to'])) {
            $query->whereHas('variants', function ($variants) use ($filters): void {
                if (isset($filters['price_from'])) {
                    $variants->where('price', '>=', $filters['price_from']);
                }
                if (isset($filters['price_to'])) {
                    $variants->where('price', '<=', $filters['price_to']);
                }
            });
        }

        return ProductResource::collection($query->orderBy('name')->paginate($filters['per_page'] ?? 25)->withQueryString());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $product = $this->managementService->create($request->user(), $request->validated());

        return (new ProductResource($product->load(['category', 'brand'])))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(['category', 'brand']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        return new ProductResource($this->managementService->update($request->user(), $product, $request->validated())->load(['category', 'brand']));
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);
        $this->managementService->delete(request()->user(), $product);

        return response()->noContent();
    }
}
