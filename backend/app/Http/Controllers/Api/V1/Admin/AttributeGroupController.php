<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAttributeGroupRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAttributeGroupRequest;
use App\Http\Resources\Catalog\AttributeGroupResource;
use App\Models\AttributeGroup;
use App\Services\AttributeGroupManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AttributeGroupController extends Controller
{
    public function __construct(private readonly AttributeGroupManagementService $managementService) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AttributeGroup::class);

        return AttributeGroupResource::collection(AttributeGroup::query()->orderBy('sort_order')->orderBy('name')->paginate(25));
    }

    public function store(StoreAttributeGroupRequest $request): JsonResponse
    {
        Gate::authorize('create', AttributeGroup::class);

        return (new AttributeGroupResource($this->managementService->create($request->user(), $request->validated())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(AttributeGroup $attributeGroup): AttributeGroupResource
    {
        Gate::authorize('view', $attributeGroup);

        return new AttributeGroupResource($attributeGroup);
    }

    public function update(UpdateAttributeGroupRequest $request, AttributeGroup $attributeGroup): AttributeGroupResource
    {
        Gate::authorize('update', $attributeGroup);

        return new AttributeGroupResource($this->managementService->update($request->user(), $attributeGroup, $request->validated()));
    }

    public function destroy(AttributeGroup $attributeGroup): Response
    {
        Gate::authorize('delete', $attributeGroup);
        $this->managementService->delete(request()->user(), $attributeGroup);

        return response()->noContent();
    }
}
