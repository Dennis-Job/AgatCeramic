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
        $query = Product::query()->with(['category', 'brand', 'primaryImage', 'groupMembership.group']);

        if ($search = $filters['search'] ?? null) {
            $pattern = '%'.mb_strtolower($search).'%';
            $query->where(function ($query) use ($pattern): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(article_number) LIKE ?', [$pattern])
                    ->orWhere('barcode', 'LIKE', $pattern);
            });
        }

        foreach (['category_id', 'brand_id', 'is_active', 'is_on_sale'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['has_stock'])) {
            $filters['has_stock'] ? $query->where('stock_quantity', '>', 0) : $query->where(fn ($query) => $query->whereNull('stock_quantity')->orWhere('stock_quantity', 0));
        }

        if (isset($filters['price_from']) || isset($filters['price_to'])) {
            if (isset($filters['price_from'])) {
                $query->where('price', '>=', $filters['price_from']);
            }
            if (isset($filters['price_to'])) {
                $query->where('price', '<=', $filters['price_to']);
            }
        }

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        return ProductResource::collection(
            $query->orderBy($sort, $direction)->orderByDesc('id')->paginate($filters['per_page'] ?? 25)->withQueryString()
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
