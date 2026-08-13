<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreRoleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleManagementService $managementService) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        return RoleResource::collection(Role::query()->with('permissions')->orderBy('name')->get());
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        Gate::authorize('create', Role::class);

        return (new RoleResource($this->managementService->create($request->user(), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Role $role): RoleResource
    {
        Gate::authorize('view', $role);

        return new RoleResource($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        Gate::authorize('update', $role);

        return new RoleResource($this->managementService->update($request->user(), $role, $request->validated()));
    }

    public function destroy(Role $role): Response
    {
        Gate::authorize('delete', $role);
        $this->managementService->delete(request()->user(), $role);

        return response()->noContent();
    }

    public function permissions(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Permission::class);

        return PermissionResource::collection(Permission::query()->orderBy('code')->get());
    }
}
