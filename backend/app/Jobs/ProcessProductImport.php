<?php

namespace App\Jobs;

use App\Services\CategoryProductImportService;
use App\Services\GenericProductImportService;
use App\Services\ImportLifecycleService;
use App\Services\ProductGroupImportService;
use App\Services\ProductPriceStatusImportService;
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
        ImportLifecycleService $lifecycle,
        GenericProductImportService $generic,
        CategoryProductImportService $categoryImport,
        ProductGroupImportService $groupImport,
        ProductPriceStatusImportService $priceStatusImport,
    ): void {
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

        // Laravel invokes failed() directly instead of through Container::call, so this callback is
        // the narrow framework adapter where resolving the application service is unavoidable.
        app(ImportLifecycleService::class)->failProductImport($this->productImportId, $message);
    }
}
