<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListPermissionsRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function index(ListPermissionsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Permission::class);

        $query = Permission::query()->with('roles')->orderBy('code');

        if ($module = $request->validated('module')) {
            $query->where('code', 'like', $module.'.%');
        }

        return PermissionResource::collection($query->get());
    }

    public function show(Permission $permission): PermissionResource
    {
        Gate::authorize('view', $permission);

        return new PermissionResource($permission->load('roles'));
    }
}
