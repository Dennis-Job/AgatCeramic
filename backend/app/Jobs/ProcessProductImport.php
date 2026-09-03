<?php

namespace App\Jobs;

use App\Enums\AdminUserStatus;
use App\Models\ProductImport;
use App\Services\CategoryProductImportService;
use App\Services\ProductImportService;
use App\Services\StorageCleanupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $productImportId) {}

    public function handle(ProductImportService $service, StorageCleanupService $cleanupService): void
    {
        $import = ProductImport::query()->with('user')->find($this->productImportId);
        if ($import === null || $import->status === 'completed') {
            return;
        }

        $import->forceFill([
            'status' => 'processing',
            'attempts' => $import->attempts + 1,
            'error_message' => null,
            'started_at' => $import->started_at ?? now(),
        ])->save();

        if ($import->user === null || $import->user->status !== AdminUserStatus::Active || ! $import->user->hasPermission('imports.manage')) {
            throw new RuntimeException('Инициатор импорта больше не имеет доступа к операции.');
        }
        $storage = Storage::disk($import->disk);
        if (! $storage->exists($import->path)) {
            throw new RuntimeException('Загруженный XLSX-файл больше не доступен.');
        }

        if ($import->category_id !== null) {
            if (! app(CategoryProductImportService::class)->process($import, $storage->path($import->path))) {
                self::dispatch($import->id);

                return;
            }
            DB::transaction(function () use ($import, $cleanupService): void {
                $import->forceFill(['status' => 'completed', 'error_message' => null, 'completed_at' => now()])->save();
                $cleanupService->schedule($import->disk, $import->path);
            });

            return;
        }

        DB::transaction(function () use ($cleanupService, $import, $service, $storage): void {
            $result = $service->import($import->user, $storage->path($import->path));
            $import->forceFill([
                'status' => 'completed',
                'created_rows' => $result['created'],
                'updated_rows' => $result['updated'],
                'processed_rows' => $result['processed'],
                'total_rows' => $result['processed'],
                'error_message' => null,
                'completed_at' => now(),
            ])->save();
            $cleanupService->schedule($import->disk, $import->path);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $import = ProductImport::query()->find($this->productImportId);
        if ($import === null || $import->status === 'completed') {
            return;
        }

        $message = $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->first()
            : null;
        $message = is_string($message) && $message !== '' ? $message : 'Не удалось обработать XLSX-файл.';
        $import->forceFill([
            'status' => 'failed',
            'error_message' => mb_substr($message, 0, 2000),
            'completed_at' => now(),
        ])->save();
        app(StorageCleanupService::class)->schedule($import->disk, $import->path);
    }
}
