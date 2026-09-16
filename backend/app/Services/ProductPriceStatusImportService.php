<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A deliberately narrow import: no category, characteristics, names, or stock can be changed here. */
class ProductPriceStatusImportService
{
    public const MAX_ROWS_PER_SHEET = ProductPriceStatusWorkbookReader::MAX_ROWS_PER_SHEET;

    public const HEADERS = ProductPriceStatusWorkbookReader::HEADERS;

    public function __construct(
        private readonly ProductPriceStatusWorkbookReader $reader,
        private readonly ProductManagementService $products,
        private readonly ProductPriceStatusTemplateService $templates,
        private readonly ProductPriceStatusErrorReportService $errorReports,
    ) {}

    /** @return array{path: string, name: string} */
    public function createTemplate(): array
    {
        return $this->templates->createTemplate();
    }

    /** Creates durable, validated work items. Invalid rows do not prevent unrelated valid rows from updating. */
    public function initialize(ProductImport $import, string $path): void
    {
        $entries = $this->reader->read($path);
        DB::transaction(function () use ($import, $entries): void {
            $import = ProductImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($import->total_rows !== 0) {
                return;
            }
            $seen = [];
            $sequence = 0;
            $failed = 0;
            foreach ($entries as $entry) {
                $sequence++;
                $messages = $this->reader->validate($seen, $entry);
                $product = null;
                if ($messages === []) {
                    $product = Product::query()->where('sku', $entry['sku'])->first();
                    if ($product === null) {
                        $messages[] = 'Товар с указанным SKU не найден.';
                    }
                }
                if ($messages !== []) {
                    $import->rowErrors()->create([
                        'row_number' => $sequence,
                        'name' => $entry['sku'] ?: null,
                        'messages' => array_map(fn (mixed $message): string => 'Лист «'.$this->reader->stringValue($entry['sheet']).'», строка '.$this->reader->stringValue($entry['row']).': '.$this->reader->stringValue($message), $messages),
                        'values' => $entry,
                    ]);
                    $failed++;

                    continue;
                }
                ProductImportItem::query()->create([
                    'product_import_id' => $import->id, 'product_id' => $product->id, 'row_number' => $sequence,
                    'name' => $entry['sku'], 'payload' => ['updates' => $entry['updates'], 'sheet' => $entry['sheet'], 'source_row' => $entry['row']],
                    'attribute_payload' => [], 'status' => 'pending',
                ]);
            }
            $import->forceFill(['total_rows' => count($entries), 'failed_rows' => $failed, 'processed_rows' => $failed])->save();
        });
        $import->refresh();
    }

    /** Process one bounded queue chunk. */
    public function process(ProductImport $import): bool
    {
        if ($import->total_rows === 0) {
            return true;
        }
        $items = $import->items()->where('status', 'pending')->limit(100)->get();
        foreach ($items as $item) {
            DB::transaction(function () use ($import, $item): void {
                $locked = ProductImportItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'pending') {
                    return;
                }
                $rawOperation = $locked->getAttribute('payload');
                $operation = is_array($rawOperation) ? $rawOperation : [];
                try {
                    $product = Product::query()->find($locked->product_id);
                    if ($product === null) {
                        throw ValidationException::withMessages(['sku' => ['Товар был удалён до обработки строки.']]);
                    }
                    $actor = $import->user;
                    if ($actor === null || ! isset($operation['updates']) || ! is_array($operation['updates'])) {
                        throw ValidationException::withMessages(['sku' => ['Не удалось обработать данные импорта.']]);
                    }
                    $this->products->update($actor, $product, $this->stringKeyed($operation['updates']));
                    $locked->forceFill(['status' => 'completed'])->save();
                    $import->increment('updated_rows');
                } catch (ValidationException $exception) {
                    $locked->forceFill(['status' => 'failed'])->save();
                    $import->rowErrors()->create([
                        'row_number' => $locked->row_number, 'name' => $locked->name,
                        'messages' => array_map(fn (mixed $message): string => 'Лист «'.$this->reader->stringValue($operation['sheet'] ?? '—').'», строка '.$this->reader->stringValue($operation['source_row'] ?? '—').': '.$this->reader->stringValue($message), collect($exception->errors())->flatten()->all()),
                        'values' => ['sheet' => $operation['sheet'] ?? '—', 'row' => $operation['source_row'] ?? '—', 'sku' => $locked->name, 'updates' => $operation['updates'] ?? []],
                    ]);
                    $import->increment('failed_rows');
                }
                $import->increment('processed_rows');
            });
        }

        return ! $import->items()->where('status', 'pending')->exists();
    }

    /** @return array{path: string, name: string} */
    public function createErrorReport(ProductImport $import): array
    {
        return $this->errorReports->create($import);
    }

    /**
     * @param  array<mixed, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyed(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
