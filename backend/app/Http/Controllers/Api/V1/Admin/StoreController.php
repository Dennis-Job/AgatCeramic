<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReplaceStoreWorkingHoursRequest;
use App\Http\Requests\Api\V1\Admin\StoreStoreRequest;
use App\Http\Requests\Api\V1\Admin\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\StoreManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class StoreController extends Controller
{
    public function __construct(private readonly StoreManagementService $managementService) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Store::class);

        return StoreResource::collection(Store::query()->with('workingHours')->orderBy('id')->paginate(25));
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        Gate::authorize('create', Store::class);

        return (new StoreResource($this->managementService->create($this->authenticatedAdmin($request), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Store $store): StoreResource
    {
        Gate::authorize('view', $store);

        return new StoreResource($store->load('workingHours'));
    }

    public function update(UpdateStoreRequest $request, Store $store): StoreResource
    {
        Gate::authorize('update', $store);

        return new StoreResource($this->managementService->update($this->authenticatedAdmin($request), $store, $request->validated()));
    }

    public function replaceWorkingHours(ReplaceStoreWorkingHoursRequest $request, Store $store): StoreResource
    {
        Gate::authorize('update', $store);

        return new StoreResource($this->managementService->replaceWorkingHours(
            $this->authenticatedAdmin($request), $store, $request->workingHours(),
        ));
    }

    public function destroy(Request $request, Store $store): Response
    {
        Gate::authorize('delete', $store);
        $this->managementService->delete($this->authenticatedAdmin($request), $store);

        return response()->noContent();
    }
}
