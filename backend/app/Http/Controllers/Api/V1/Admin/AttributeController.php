<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAttributeRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAttributeRequest;
use App\Http\Resources\Catalog\AttributeResource;
use App\Models\Attribute;
use App\Services\AttributeManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AttributeController extends Controller
{
    public function __construct(private readonly AttributeManagementService $managementService) {}

    public function index(): mixed
    {
        Gate::authorize('viewAny', Attribute::class);

        return AttributeResource::collection(Attribute::query()->with('options')->orderBy('sort_order')->orderBy('name')->paginate(25));
    }

    public function store(StoreAttributeRequest $request): JsonResponse
    {
        Gate::authorize('create', Attribute::class);

        return (new AttributeResource($this->managementService->create($request->user(), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Attribute $attribute): AttributeResource
    {
        Gate::authorize('view', $attribute);

        return new AttributeResource($attribute->load('options'));
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): AttributeResource
    {
        Gate::authorize('update', $attribute);

        return new AttributeResource($this->managementService->update($request->user(), $attribute, $request->validated()));
    }

    public function destroy(Request $request, Attribute $attribute): Response
    {
        Gate::authorize('delete', $attribute);
        $this->managementService->delete($request->user(), $attribute);

        return response()->noContent();
    }
}
