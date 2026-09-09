<?php

namespace App\Jobs;

use App\Services\ImportLifecycleService;
use App\Services\ProductImageImportService;
use App\Services\StorageCleanupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessProductImageImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $productImageImportId) {}

    public function handle(ProductImageImportService $service, StorageCleanupService $cleanup, ?ImportLifecycleService $lifecycle = null): void
    {
        $lifecycle ??= app(ImportLifecycleService::class);
        $import = $lifecycle->startProductImageImport($this->productImageImportId);
        if ($import === null) {
            return;
        }

        $service->process($import)
            ? $lifecycle->completeProductImageImport($import)
            : $lifecycle->continueProductImageImport($import);
    }

    public function failed(?Throwable $exception): void
    {
        app(ImportLifecycleService::class)->failProductImageImport(
            $this->productImageImportId,
            $exception?->getMessage() ?: 'Не удалось обработать ZIP-архив.',
        );
    }
}
