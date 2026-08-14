<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReplaceProductAttributeValuesRequest;
use App\Http\Resources\Catalog\ProductAttributeValueResource;
use App\Models\Product;
use App\Services\ProductAttributeValueManagementService;
use Illuminate\Support\Facades\Gate;

class ProductAttributeValueController extends Controller
{
    public function __construct(private readonly ProductAttributeValueManagementService $managementService) {}

    public function index(Product $product): mixed
    {
        Gate::authorize('view', $product);

        return ProductAttributeValueResource::collection($product->attributeValues()->with('attribute.options')->get());
    }

    public function replace(ReplaceProductAttributeValuesRequest $request, Product $product): mixed
    {
        Gate::authorize('update', $product);

        return ProductAttributeValueResource::collection(
            $this->managementService->replace($request->user(), $product, $request->validated('attributes'))->attributeValues,
        );
    }
}
