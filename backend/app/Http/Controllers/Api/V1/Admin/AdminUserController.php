<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListAdminUsersRequest;
use App\Http\Requests\Api\V1\Admin\StoreAdminUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminUserRequest;
use App\Http\Resources\AdminRoleResource;
use App\Http\Resources\AdminUserResource;
use App\Models\Role;
use App\Models\User;
use App\Queries\AdminUserQuery;
use App\Services\AdminUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserManagementService $managementService,
        private readonly AdminUserQuery $users,
    ) {}

    public function index(ListAdminUsersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return AdminUserResource::collection(
            $this->users->paginate($request->validated(), $request->integer('per_page', 20)),
        );
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        return (new AdminUserResource($this->managementService->create($this->authenticatedAdmin($request), $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): AdminUserResource
    {
        Gate::authorize('view', $user);

        return new AdminUserResource($this->users->prepare($user));
    }

    public function update(UpdateAdminUserRequest $request, User $user): AdminUserResource
    {
        Gate::authorize('update', $user);

        $attributes = $request->validated();
        $admin = $this->authenticatedAdmin($request);
        $updatedUser = $this->managementService->update($admin, $user, $attributes);

        if (array_key_exists('password', $attributes) && $admin->is($user)) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return new AdminUserResource($updatedUser);
    }

    public function destroy(Request $request, User $user): Response
    {
        Gate::authorize('delete', $user);
        $this->managementService->delete($this->authenticatedAdmin($request), $user);

        return response()->noContent();
    }

    public function roles(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return AdminRoleResource::collection(Role::query()->orderBy('name')->get());
    }
}
