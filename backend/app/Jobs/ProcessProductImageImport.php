<?php

namespace App\Jobs;

use App\Enums\AdminUserStatus;
use App\Models\ProductImageImport;
use App\Services\ProductImageImportService;
use App\Services\StorageCleanupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessProductImageImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $productImageImportId) {}

    public function handle(ProductImageImportService $service, StorageCleanupService $cleanup): void
    {
        $import = ProductImageImport::query()->with('user')->find($this->productImageImportId);
        if ($import === null || $import->status === 'completed') {
            return;
        }
        $import->forceFill(['status' => 'processing', 'attempts' => $import->attempts + 1, 'error_message' => null, 'started_at' => $import->started_at ?? now()])->save();
        if ($import->user === null || $import->user->status !== AdminUserStatus::Active || ! $import->user->hasPermission('imports.manage')) {
            throw new RuntimeException('Инициатор импорта больше не имеет доступа к операции.');
        }
        if (! Storage::disk($import->disk)->exists($import->path)) {
            throw new RuntimeException('Загруженный ZIP-файл больше не доступен.');
        }
        if (! $service->process($import)) {
            self::dispatch($import->id);

            return;
        }
        $import->forceFill(['status' => 'completed', 'completed_at' => now(), 'error_message' => null])->save();
        $cleanup->schedule($import->disk, $import->path);
    }

    public function failed(?Throwable $exception): void
    {
        $import = ProductImageImport::query()->find($this->productImageImportId);
        if ($import === null || $import->status === 'completed') {
            return;
        }
        $import->forceFill(['status' => 'failed', 'error_message' => mb_substr($exception?->getMessage() ?: 'Не удалось обработать ZIP-архив.', 0, 2000), 'completed_at' => now()])->save();
        app(StorageCleanupService::class)->schedule($import->disk, $import->path);
    }
}
