<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ProductImport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryProductImportService
{
    public function __construct(
        private readonly ProductImportTemplateService $templateService,
        private readonly ProductImportService $importService,
    ) {}

    /** Process a bounded chunk. Every product and checkpoint commit together, so retries resume safely. */
    public function process(ProductImport $import, string $path): bool
    {
        $category = Category::query()->find($import->category_id);
        if ($category === null) {
            throw ValidationException::withMessages(['category_id' => ['Категория шаблона больше не существует.']]);
        }
        $entries = $this->templateService->read($category, $path);
        $import->forceFill(['total_rows' => count($entries)])->save();
        $processed = 0;
        $started = microtime(true);
        foreach ($entries as $entry) {
            if ($entry['row'] <= $import->last_processed_row) {
                continue;
            }
            DB::transaction(function () use ($import, $category, $entry): void {
                $locked = ProductImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
                if ($entry['row'] <= $locked->last_processed_row) {
                    $import->setRawAttributes($locked->getAttributes(), true);

                    return;
                }
                try {
                    DB::transaction(fn () => $this->importService->createTemplateRow($import->user, $category, $entry['values'], $entry['row']));
                    $locked->created_rows++;
                } catch (ValidationException|UniqueConstraintViolationException $exception) {
                    $locked->rowErrors()->create([
                        'row_number' => $entry['row'],
                        'name' => mb_substr((string) ($entry['values']['name'] ?? ''), 0, 255),
                        'messages' => $exception instanceof ValidationException
                            ? collect($exception->errors())->flatten()->values()->all()
                            : ['Товар с таким slug, артикулом или штрихкодом уже существует.'],
                        'values' => $entry['values'],
                    ]);
                    $locked->failed_rows++;
                }
                $locked->processed_rows++;
                $locked->last_processed_row = $entry['row'];
                $locked->save();
                $import->setRawAttributes($locked->getAttributes(), true);
            });
            $processed++;
            if ($processed >= 100 || microtime(true) - $started >= 35) {
                break;
            }
        }

        return $import->processed_rows >= $import->total_rows;
    }
}
