<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = $request->validated();
        $query = AuditLog::query()->with(['actor:id,name', 'entity'])->latest('occurred_at');

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($query) use ($search): void {
                $query->where('action', 'like', '%'.$search.'%')
                    ->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($action = $filters['action'] ?? null) {
            $query->where('action', $action);
        }

        if ($actorId = $filters['actor_id'] ?? null) {
            $query->where('actor_id', $actorId);
        }

        if ($dateFrom = $filters['date_from'] ?? null) {
            $query->where('occurred_at', '>=', $dateFrom.' 00:00:00');
        }

        if ($dateTo = $filters['date_to'] ?? null) {
            $query->where('occurred_at', '<=', $dateTo.' 23:59:59');
        }

        $logs = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        $this->resolveMetadataReferences($logs->getCollection());

        return AuditLogResource::collection($logs);
    }

    public function show(AuditLog $auditLog): AuditLogResource
    {
        Gate::authorize('view', $auditLog);

        $auditLog->load(['actor:id,name', 'entity']);
        $this->resolveMetadataReferences(collect([$auditLog]));

        return new AuditLogResource($auditLog);
    }

    /** @param Collection<int, AuditLog> $logs */
    private function resolveMetadataReferences(Collection $logs): void
    {
        $roleIds = $logs->flatMap(fn (AuditLog $log): array => $log->metadata['role_ids'] ?? [])->unique()->values();
        $permissionIds = $logs->flatMap(fn (AuditLog $log): array => $log->metadata['permission_ids'] ?? [])->unique()->values();
        $roles = Role::query()->whereKey($roleIds)->pluck('name', 'id');
        $permissions = Permission::query()->whereKey($permissionIds)->pluck('name', 'id');

        $logs->each(function (AuditLog $log) use ($roles, $permissions): void {
            $metadata = $log->metadata ?? [];
            $log->setAttribute('audit_role_names', collect($metadata['role_ids'] ?? [])
                ->map(fn (int $id): string => $roles->get($id, "Роль #{$id}"))->all());
            $log->setAttribute('audit_permission_names', collect($metadata['permission_ids'] ?? [])
                ->map(fn (int $id): string => $permissions->get($id, "Право #{$id}"))->all());
        });
    }
}
