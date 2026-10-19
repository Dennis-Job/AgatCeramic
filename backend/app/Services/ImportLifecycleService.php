<?php

namespace App\Services;

use App\Enums\AdminUserStatus;
use App\Models\AuditLog;
use App\Models\ProductImageImport;
use App\Models\ProductImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportLifecycleService
{
    public function __construct(
        private readonly ImportDispatchService $dispatchService,
        private readonly PermissionChecker $permissionChecker,
        private readonly StorageCleanupService $cleanupService,
        private readonly AuditLogService $audit,
    ) {}

    public function startProductImport(int $id): ?ProductImport
    {
        return DB::transaction(function () use ($id): ?ProductImport {
            $import = ProductImport::query()->with('user')->whereKey($id)->lockForUpdate()->first();
            if ($import === null || in_array($import->status, ['completed', 'failed'], true)) {
                return null;
            }
            if ($import->user === null || $import->user->getRawOriginal('status') !== AdminUserStatus::Active->value || ! $this->permissionChecker->allows($import->user, 'imports.manage') || ($import->operation === 'group' && ! $this->permissionChecker->allows($import->user, 'catalog.manage'))) {
                throw new RuntimeException('Инициатор импорта больше не имеет доступа к операции.');
            }
            if (! Storage::disk($import->disk)->exists($import->path)) {
                throw new RuntimeException('Загруженный XLSX-файл больше не доступен.');
            }

            $import->forceFill([
                'status' => 'processing',
                'attempts' => $import->attempts + 1,
                'error_message' => null,
                'started_at' => $import->started_at ?? now(),
            ])->save();

            return $import;
        });
    }

    public function startProductImageImport(int $id): ?ProductImageImport
    {
        return DB::transaction(function () use ($id): ?ProductImageImport {
            $import = ProductImageImport::query()->with('user')->whereKey($id)->lockForUpdate()->first();
            if ($import === null || in_array($import->status, ['completed', 'failed'], true)) {
                return null;
            }
            if ($import->user === null || $import->user->getRawOriginal('status') !== AdminUserStatus::Active->value || ! $this->permissionChecker->allows($import->user, 'imports.manage')) {
                throw new RuntimeException('Инициатор импорта больше не имеет доступа к операции.');
            }
            if (! Storage::disk($import->disk)->exists($import->path)) {
                throw new RuntimeException('Загруженный ZIP-файл больше не доступен.');
            }

            $import->forceFill([
                'status' => 'processing',
                'attempts' => $import->attempts + 1,
                'error_message' => null,
                'started_at' => $import->started_at ?? now(),
            ])->save();

            return $import;
        });
    }

    public function continueProductImport(ProductImport $import): void
    {
        DB::transaction(fn () => $this->dispatchService->continueProductImport($import));
    }

    public function continueProductImageImport(ProductImageImport $import): void
    {
        DB::transaction(fn () => $this->dispatchService->continueProductImageImport($import));
    }

    public function completeProductImport(ProductImport $import): void
    {
        DB::transaction(function () use ($import): void {
            $locked = ProductImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'failed') {
                return;
            }
            if ($locked->status !== 'completed') {
                $locked->forceFill(['status' => 'completed', 'error_message' => null, 'completed_at' => now()])->save();
            }
            $this->recordTerminal($locked, 'import.completed');
            $this->cleanupService->schedule($locked->disk, $locked->path);
            $this->dispatchService->completeProductImport($locked);
        });
    }

    public function completeProductImageImport(ProductImageImport $import): void
    {
        DB::transaction(function () use ($import): void {
            $locked = ProductImageImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'failed') {
                return;
            }
            if ($locked->status !== 'completed') {
                $locked->forceFill(['status' => 'completed', 'error_message' => null, 'completed_at' => now()])->save();
            }
            $this->recordTerminal($locked, 'image-import.completed');
            $this->cleanupService->schedule($locked->disk, $locked->path);
            $this->dispatchService->completeProductImageImport($locked);
        });
    }

    public function failProductImport(int $id, string $message): void
    {
        DB::transaction(function () use ($id, $message): void {
            $import = ProductImport::query()->whereKey($id)->lockForUpdate()->first();
            if ($import === null || in_array($import->status, ['completed', 'failed'], true)) {
                return;
            }
            $import->forceFill(['status' => 'failed', 'error_message' => mb_substr($message, 0, 2000), 'completed_at' => now()])->save();
            $this->recordTerminal($import, 'import.failed');
            $this->cleanupService->schedule($import->disk, $import->path);
            $this->dispatchService->completeProductImport($import);
        });
    }

    public function failProductImageImport(int $id, string $message): void
    {
        DB::transaction(function () use ($id, $message): void {
            $import = ProductImageImport::query()->whereKey($id)->lockForUpdate()->first();
            if ($import === null || in_array($import->status, ['completed', 'failed'], true)) {
                return;
            }
            $import->forceFill(['status' => 'failed', 'error_message' => mb_substr($message, 0, 2000), 'completed_at' => now()])->save();
            $this->recordTerminal($import, 'image-import.failed');
            $this->cleanupService->schedule($import->disk, $import->path);
            $this->dispatchService->completeProductImageImport($import);
        });
    }

    private function recordTerminal(ProductImport|ProductImageImport $import, string $action): void
    {
        // The import row is locked by the caller; preflight may already have completed it.
        if (AuditLog::query()->where('entity_type', $import::class)->where('entity_id', $import->id)->where('action', $action)->exists()) {
            return;
        }

        $counts = $import instanceof ProductImport
            ? ['total' => $import->total_rows, 'processed' => $import->processed_rows, 'created' => $import->created_rows, 'updated' => $import->updated_rows, 'failed' => $import->failed_rows]
            : ['total' => $import->total_folders, 'processed' => $import->processed_folders, 'created' => $import->created_images, 'replaced' => $import->replaced_images, 'failed' => $import->failed_folders];
        // Employee deletion may clear user_id; the immutable submission retains its initiator.
        $initiatedBy = $import->user === null
            ? AuditLog::query()->where('entity_type', $import::class)->where('entity_id', $import->id)
                ->where('action', $import instanceof ProductImport ? 'import.submitted' : 'image-import.submitted')->first()
            : null;
        $this->audit->record($import->user, $action, $import, [
            'import_id' => $import->id,
            'operation' => $import instanceof ProductImport ? ($import->operation ?? 'product') : 'image',
            ...$counts,
        ], $initiatedBy);
    }
}
