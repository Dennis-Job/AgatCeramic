<?php

namespace App\Queries;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AuditLogQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = AuditLog::query()->with(['actor:id,name', 'entity']);

        $search = $filters['search'] ?? null;
        $search = is_string($search) ? trim($search) : '';
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('action', 'like', '%'.$search.'%')
                    ->orWhereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', '%'.$search.'%'));
            });
        }

        $action = $filters['action'] ?? null;
        $action = is_string($action) ? trim($action) : '';
        if ($action !== '') {
            $query->where('action', $action);
        }

        $actorId = $filters['actor_id'] ?? null;
        if (is_int($actorId) || (is_string($actorId) && ctype_digit($actorId))) {
            $query->where('actor_id', (int) $actorId);
        }

        $dateFrom = $filters['date_from'] ?? null;
        if (is_string($dateFrom) && $dateFrom !== '') {
            $query->where('occurred_at', '>=', $dateFrom.' 00:00:00');
        }

        $dateTo = $filters['date_to'] ?? null;
        if (is_string($dateTo) && $dateTo !== '') {
            $query->where('occurred_at', '<=', $dateTo.' 23:59:59');
        }

        $logs = $query->latest('occurred_at')->paginate($perPage)->withQueryString();
        $this->resolveMetadataReferences($logs->getCollection());

        return $logs;
    }

    public function prepare(AuditLog $auditLog): AuditLog
    {
        $auditLog->load(['actor:id,name', 'entity']);
        $this->resolveMetadataReferences(collect([$auditLog]));

        return $auditLog;
    }

    /** @param Collection<int, AuditLog> $logs */
    private function resolveMetadataReferences(Collection $logs): void
    {
        [$roleIds, $permissionIds] = $this->metadataReferenceIds($logs);
        $roles = Role::query()->whereKey($roleIds)->pluck('name', 'id');
        $permissions = Permission::query()->whereKey($permissionIds)->pluck('name', 'id');

        $logs->each(function (AuditLog $log) use ($roles, $permissions): void {
            $metadata = is_array($log->metadata) ? $log->metadata : [];
            $log->setAttribute('audit_role_names', $this->referenceNames($metadata['role_ids'] ?? [], $roles, 'Роль'));
            $log->setAttribute('audit_permission_names', $this->referenceNames($metadata['permission_ids'] ?? [], $permissions, 'Право'));
        });
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array{list<int>, list<int>}
     */
    private function metadataReferenceIds(Collection $logs): array
    {
        $roleIds = [];
        $permissionIds = [];

        foreach ($logs as $log) {
            $metadata = is_array($log->metadata) ? $log->metadata : [];
            $roleIds = [...$roleIds, ...$this->integerIds($metadata['role_ids'] ?? [])];
            $permissionIds = [...$permissionIds, ...$this->integerIds($metadata['permission_ids'] ?? [])];
        }

        return [array_values(array_unique($roleIds)), array_values(array_unique($permissionIds))];
    }

    /** @return list<int> */
    private function integerIds(mixed $ids): array
    {
        return is_array($ids) ? array_values(array_filter($ids, is_int(...))) : [];
    }

    /**
     * @param  Collection<int|string, mixed>  $references
     * @return list<string>
     */
    private function referenceNames(mixed $ids, Collection $references, string $fallback): array
    {
        $names = [];
        foreach ($this->integerIds($ids) as $id) {
            $name = $references->get($id);
            $names[] = is_string($name) ? $name : "{$fallback} #{$id}";
        }

        return $names;
    }
}
