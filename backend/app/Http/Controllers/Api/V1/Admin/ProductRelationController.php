<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProductRelationCandidatesRequest;
use App\Http\Requests\Api\V1\Admin\ReplaceProductRelationsRequest;
use App\Http\Resources\Catalog\ProductRelationResource;
use App\Http\Resources\Catalog\ProductResource;
use App\Models\Product;
use App\Queries\ProductRelationCandidateQuery;
use App\Services\ProductRelationManagementService;
use Illuminate\Support\Facades\Gate;

class ProductRelationController extends Controller
{
    public function __construct(
        private readonly ProductRelationManagementService $managementService,
        private readonly ProductRelationCandidateQuery $relationCandidates,
    ) {}

    public function index(Product $product): mixed
    {
        Gate::authorize('view', $product);

        return ProductRelationResource::collection($product->outgoingRelations()->with('relatedProduct')->orderBy('sort_order')->orderBy('type')->get());
    }

    public function candidates(ListProductRelationCandidatesRequest $request, Product $product): mixed
    {
        Gate::authorize('view', $product);

        return ProductResource::collection($this->relationCandidates->get(
            $product,
            $request->string('search')->trim()->toString() ?: null,
            $request->integer('limit', 20),
        ));
    }

    public function replace(ReplaceProductRelationsRequest $request, Product $product): mixed
    {
        Gate::authorize('update', $product);

        $updatedProduct = $this->managementService->replace($this->authenticatedAdmin($request), $product, $request->relations());

        return ProductRelationResource::collection(
            $updatedProduct->outgoingRelations()->with('relatedProduct')->orderBy('sort_order')->orderBy('type')->get(),
        );
    }
}
