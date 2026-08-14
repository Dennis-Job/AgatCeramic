<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BrandManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Brand
    {
        return DB::transaction(function () use ($actor, $attributes): Brand {
            $brand = Brand::query()->create($attributes);
            $this->auditLogService->record($actor, 'brand.created', $brand);

            return $brand;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Brand $brand, array $attributes): Brand
    {
        return DB::transaction(function () use ($actor, $brand, $attributes): Brand {
            $brand->fill($attributes)->save();
            $this->auditLogService->record($actor, 'brand.updated', $brand);

            return $brand;
        });
    }

    public function delete(User $actor, Brand $brand): void
    {
        DB::transaction(function () use ($actor, $brand): void {
            $this->auditLogService->record($actor, 'brand.deleted', $brand);
            $brand->delete();
        });
    }
}
