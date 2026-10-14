<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BrandManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MediaManagementService $mediaService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Brand
    {
        return DB::transaction(function () use ($actor, $attributes): Brand {
            $documentIds = $attributes['document_ids'] ?? [];
            unset($attributes['document_ids']);
            $brand = Brand::query()->create($attributes);
            $brand->documents()->sync($this->documentOrder($documentIds));
            $this->auditLogService->record($actor, 'brand.created', $brand);

            return $brand->load(['logo', 'documents']);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Brand $brand, array $attributes): Brand
    {
        return DB::transaction(function () use ($actor, $brand, $attributes): Brand {
            $brand = Brand::query()->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $originalLogoId = $brand->logo_id;
            $documentIds = $attributes['document_ids'] ?? null;
            unset($attributes['document_ids']);
            $brand->fill($attributes)->save();
            if ($documentIds !== null) {
                $brand->documents()->sync($this->documentOrder($documentIds));
            }
            $this->auditLogService->record($actor, 'brand.updated', $brand);
            if ($originalLogoId !== null && $brand->logo_id !== $originalLogoId) {
                $this->mediaService->deleteIfUnused($actor, $originalLogoId);
            }

            return $brand->load(['logo', 'documents']);
        }, 3);
    }

    public function delete(User $actor, Brand $brand): void
    {
        DB::transaction(function () use ($actor, $brand): void {
            $brand = Brand::query()->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $logoId = $brand->logo_id;
            $this->auditLogService->record($actor, 'brand.deleted', $brand);
            $brand->delete();
            if ($logoId !== null) {
                $this->mediaService->deleteIfUnused($actor, $logoId);
            }
        }, 3);
    }

    /** @return array<int, array{sort_order: int}> */
    private function documentOrder(mixed $ids): array
    {
        if (! is_array($ids)) {
            throw new \InvalidArgumentException('Document IDs must be an array.');
        }
        $ordered = [];
        $position = 0;
        foreach ($ids as $id) {
            if (! is_int($id)) {
                throw new \InvalidArgumentException('Document IDs must be integers.');
            }
            $ordered[$id] = ['sort_order' => $position];
            $position++;
        }

        return $ordered;
    }
}
