<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReplaceProductRelationsRequest;
use App\Http\Resources\Catalog\ProductRelationResource;
use App\Models\Product;
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

    public function replace(ReplaceProductRelationsRequest $request, Product $product): mixed
    {
        Gate::authorize('update', $product);

        $updatedProduct = $this->managementService->replace($request->user(), $product, $request->validated('relations'));

        return ProductRelationResource::collection(
            $updatedProduct->outgoingRelations()->with('relatedProduct')->orderBy('sort_order')->orderBy('type')->get(),
        );
    }
}
