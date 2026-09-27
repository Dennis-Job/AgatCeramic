<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Store
    {
        return DB::transaction(function () use ($actor, $attributes): Store {
            $store = Store::query()->create($attributes);
            $store->workingHours()->createMany(array_map(
                static fn (int $weekday): array => ['weekday' => $weekday, 'is_closed' => true],
                range(1, 7),
            ));
            $this->auditLogService->record($actor, 'store.created', $store);

            return $store->refresh()->load('workingHours');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Store $store, array $attributes): Store
    {
        return DB::transaction(function () use ($actor, $store, $attributes): Store {
            $store->fill($attributes)->save();
            $this->auditLogService->record($actor, 'store.updated', $store);

            return $store->load('workingHours');
        });
    }

    public function delete(User $actor, Store $store): void
    {
        DB::transaction(function () use ($actor, $store): void {
            $this->auditLogService->record($actor, 'store.deleted', $store);
            $store->delete();
        });
    }

    /** @param list<array{weekday:int,is_closed:bool,opens_at:?string,closes_at:?string}> $hours */
    public function replaceWorkingHours(User $actor, Store $store, array $hours): Store
    {
        return DB::transaction(function () use ($actor, $store, $hours): Store {
            $store->workingHours()->delete();
            $store->workingHours()->createMany(array_map(static fn (array $day): array => [
                'weekday' => $day['weekday'],
                'is_closed' => $day['is_closed'],
                'opens_at' => $day['is_closed'] ? null : $day['opens_at'],
                'closes_at' => $day['is_closed'] ? null : $day['closes_at'],
            ], $hours));
            $this->auditLogService->record($actor, 'store.working-hours-updated', $store);

            return $store->load('workingHours');
        });
    }
}
