<?php

namespace App\Jobs;

use App\Services\CategoryProductImportService;
use App\Services\GenericProductImportService;
use App\Services\ImportLifecycleService;
use App\Services\ProductGroupImportService;
use App\Services\ProductImportService;
use App\Services\ProductPriceStatusImportService;
use App\Services\StorageCleanupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $productImportId) {}

    public function handle(
        ProductImportService $service,
        StorageCleanupService $cleanupService,
        ?ImportLifecycleService $lifecycle = null,
        ?GenericProductImportService $generic = null,
        ?CategoryProductImportService $categoryImport = null,
        ?ProductGroupImportService $groupImport = null,
        ?ProductPriceStatusImportService $priceStatusImport = null,
    ): void {
        // Optional arguments keep direct legacy test invocations compatible; queued execution injects all services.
        $lifecycle ??= app(ImportLifecycleService::class);
        $generic ??= app(GenericProductImportService::class);
        $categoryImport ??= app(CategoryProductImportService::class);
        $groupImport ??= app(ProductGroupImportService::class);
        $priceStatusImport ??= app(ProductPriceStatusImportService::class);

        $import = $lifecycle->startProductImport($this->productImportId);
        if ($import === null) {
            return;
        }
        $path = Storage::disk($import->disk)->path($import->path);

        if ($import->operation === 'price_status') {
            if ($import->total_rows === 0) {
                $priceStatusImport->initialize($import, $path);
            }
            $priceStatusImport->process($import)
                ? $lifecycle->completeProductImport($import)
                : $lifecycle->continueProductImport($import);

            return;
        }

        if ($import->operation === 'group') {
            if ($import->total_rows === 0) {
                $groupImport->initialize($import, $path);
            }
            $groupImport->process($import)
                ? $lifecycle->completeProductImport($import)
                : $lifecycle->continueProductImport($import);

            return;
        }

        if ($import->category_id !== null) {
            $categoryImport->process($import, $path)
                ? $lifecycle->completeProductImport($import)
                : $lifecycle->continueProductImport($import);

            return;
        }

        if ($import->total_rows === 0 && $generic->initialize($import, $path)) {
            $lifecycle->completeProductImport($import);

            return;
        }
        $generic->process($import)
            ? $lifecycle->completeProductImport($import)
            : $lifecycle->continueProductImport($import);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->first()
            : null;
        $message = is_string($message) && $message !== '' ? $message : 'Не удалось обработать XLSX-файл.';

        app(ImportLifecycleService::class)->failProductImport($this->productImportId, $message);
    }
}
