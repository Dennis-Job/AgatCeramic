<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProductRelationCandidatesRequest;
use App\Http\Requests\Api\V1\Admin\ReplaceProductRelationsRequest;
use App\Http\Resources\Catalog\ProductRelationResource;
use App\Http\Resources\Catalog\ProductResource;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Services\ProductRelationManagementService;
use Illuminate\Support\Facades\Gate;

class ProductRelationController extends Controller
{
    public function __construct(private readonly ProductRelationManagementService $managementService) {}

    public function index(Product $product): mixed
    {
        Gate::authorize('view', $product);

        return ProductRelationResource::collection($product->outgoingRelations()->with('relatedProduct')->orderBy('sort_order')->orderBy('type')->get());
    }

    public function candidates(ListProductRelationCandidatesRequest $request, Product $product): mixed
    {
        Gate::authorize('view', $product);
        $filters = $request->validated();
        $excludedIds = $product->outgoingRelations()->pluck('related_product_id')
            ->merge(ProductRelation::query()->where('related_product_id', $product->id)->pluck('product_id'))
            ->push($product->id)->unique();
        $query = Product::query()->with(['category', 'brand', 'primaryImage'])->whereNotIn('id', $excludedIds);
        if ($search = $filters['search'] ?? null) {
            $pattern = '%'.mb_strtolower($search).'%';
            $query->where(fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$pattern]));
        }

        return ProductResource::collection($query->orderBy('name')->limit($filters['limit'] ?? 20)->get());
    }

    public function replace(ReplaceProductRelationsRequest $request, Product $product): mixed
    {
        Gate::authorize('update', $product);

        $updatedProduct = $this->managementService->replace($request->user(), $product, $request->validated('relations'));

        return ProductRelationResource::collection(
            $updatedProduct->outgoingRelations()->with('relatedProduct')->orderBy('sort_order')->orderBy('type')->get(),
        );
    }
}
