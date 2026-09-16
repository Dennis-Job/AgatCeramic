<?php

namespace App\Jobs;

use App\Services\ImportLifecycleService;
use App\Services\ProductImageImportService;
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

    public function handle(ProductImageImportService $service, ImportLifecycleService $lifecycle): void
    {
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
        // Laravel invokes failed() directly instead of through Container::call, so this callback is
        // the narrow framework adapter where resolving the application service is unavoidable.
        app(ImportLifecycleService::class)->failProductImageImport(
            $this->productImageImportId,
            $exception?->getMessage() ?: 'Не удалось обработать ZIP-архив.',
        );
    }
}
