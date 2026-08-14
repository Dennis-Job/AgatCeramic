<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReplaceCategoryAttributeGroupsRequest;
use App\Http\Requests\Api\V1\Admin\ReplaceCategoryAttributesRequest;
use App\Http\Resources\Catalog\AttributeGroupResource;
use App\Http\Resources\Catalog\AttributeResource;
use App\Models\Category;
use App\Services\CategoryAttributeManagementService;
use Illuminate\Support\Facades\Gate;

class CategoryAttributeController extends Controller
{
    public function __construct(private readonly CategoryAttributeManagementService $managementService) {}

    public function index(Category $category): mixed
    {
        Gate::authorize('view', $category);

        return AttributeResource::collection($category->attributes()->with('options')->get());
    }

    public function replace(ReplaceCategoryAttributesRequest $request, Category $category): mixed
    {
        Gate::authorize('update', $category);

        return AttributeResource::collection(
            $this->managementService->replace($request->user(), $category, $request->validated('attributes'))
                ->attributes,
        );
    }

    public function groups(Category $category): mixed
    {
        Gate::authorize('view', $category);

        return AttributeGroupResource::collection($category->attributeGroups()->get());
    }

    public function replaceGroups(ReplaceCategoryAttributeGroupsRequest $request, Category $category): mixed
    {
        Gate::authorize('update', $category);

        return AttributeGroupResource::collection($this->managementService->replaceGroups($request->user(), $category, $request->validated('attribute_groups'))->attributeGroups);
    }
}
