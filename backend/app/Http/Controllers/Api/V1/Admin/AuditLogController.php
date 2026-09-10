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

        $query = AuditLog::query()->with(['actor:id,name', 'entity'])->latest('occurred_at');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($query) use ($search): void {
                $query->where('action', 'like', '%'.$search.'%')
                    ->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($action = $request->string('action')->trim()->toString()) {
            $query->where('action', $action);
        }

        if ($actorId = $request->integer('actor_id')) {
            $query->where('actor_id', $actorId);
        }

        if ($dateFrom = $request->string('date_from')->toString()) {
            $query->where('occurred_at', '>=', $dateFrom.' 00:00:00');
        }

        if ($dateTo = $request->string('date_to')->toString()) {
            $query->where('occurred_at', '<=', $dateTo.' 23:59:59');
        }

        $logs = $query->paginate($request->integer('per_page', 25))->withQueryString();
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
        $roleIds = [];
        $permissionIds = [];

        foreach ($logs as $log) {
            $metadata = $log->metadata;
            if (! is_array($metadata)) {
                continue;
            }

            $metadataRoleIds = $metadata['role_ids'] ?? [];
            if (is_array($metadataRoleIds)) {
                foreach ($metadataRoleIds as $id) {
                    if (is_int($id)) {
                        $roleIds[] = $id;
                    }
                }
            }
            $metadataPermissionIds = $metadata['permission_ids'] ?? [];
            if (is_array($metadataPermissionIds)) {
                foreach ($metadataPermissionIds as $id) {
                    if (is_int($id)) {
                        $permissionIds[] = $id;
                    }
                }
            }
        }

        $roleIds = array_values(array_unique($roleIds));
        $permissionIds = array_values(array_unique($permissionIds));
        $roles = Role::query()->whereKey($roleIds)->pluck('name', 'id');
        $permissions = Permission::query()->whereKey($permissionIds)->pluck('name', 'id');

        $logs->each(function (AuditLog $log) use ($roles, $permissions): void {
            $metadata = is_array($log->metadata) ? $log->metadata : [];
            $roleNames = [];
            $metadataRoleIds = $metadata['role_ids'] ?? [];
            if (is_array($metadataRoleIds)) {
                foreach ($metadataRoleIds as $id) {
                    if (is_int($id)) {
                        $name = $roles->get($id);
                        $roleNames[] = is_string($name) ? $name : "Роль #{$id}";
                    }
                }
            }
            $permissionNames = [];
            $metadataPermissionIds = $metadata['permission_ids'] ?? [];
            if (is_array($metadataPermissionIds)) {
                foreach ($metadataPermissionIds as $id) {
                    if (is_int($id)) {
                        $name = $permissions->get($id);
                        $permissionNames[] = is_string($name) ? $name : "Право #{$id}";
                    }
                }
            }
            $log->setAttribute('audit_role_names', $roleNames);
            $log->setAttribute('audit_permission_names', $permissionNames);
        });
    }
}
