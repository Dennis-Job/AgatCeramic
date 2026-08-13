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
use App\Services\AdminUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminUserManagementService $managementService) {}

    public function index(ListAdminUsersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validated();

        $users = User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, static function ($query, string $search): void {
                $pattern = '%'.mb_strtolower($search).'%';
                $query->where(static function ($query) use ($pattern): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
                });
            })
            ->when($filters['status'] ?? null, static fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return AdminUserResource::collection($users);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        return (new AdminUserResource($this->managementService->create($request->user(), $request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): AdminUserResource
    {
        Gate::authorize('view', $user);

        return new AdminUserResource($user->load('roles'));
    }

    public function update(UpdateAdminUserRequest $request, User $user): AdminUserResource
    {
        Gate::authorize('update', $user);

        return new AdminUserResource($this->managementService->update($request->user(), $user, $request->validated()));
    }

    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);
        $this->managementService->delete(request()->user(), $user);

        return response()->noContent();
    }

    public function roles(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return AdminRoleResource::collection(Role::query()->orderBy('name')->get());
    }
}
