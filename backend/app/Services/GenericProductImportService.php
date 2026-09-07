<?php

namespace App\Services;

use App\Models\ProductImport;
use App\Models\ProductImportItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenericProductImportService
{
    private const CHUNK_SIZE = 100;

    private const MAX_SECONDS = 35;

    public function __construct(private readonly ProductImportService $importService) {}

    /** Create durable queued rows, or finish immediately when preflight finds errors. */
    public function initialize(ProductImport $import, string $path): bool
    {
        $plan = $this->importService->prepareForQueue($path);

        return DB::transaction(function () use ($import, $plan): bool {
            $locked = ProductImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->total_rows > 0 || $locked->status === 'completed') {
                $import->setRawAttributes($locked->getAttributes(), true);

                return $locked->status === 'completed';
            }
            $locked->forceFill(['total_rows' => $plan['total']]);
            if ($plan['errors'] !== []) {
                $locked->rowErrors()->createMany(array_map(static fn (array $error): array => [
                    'row_number' => $error['row'], 'name' => $error['name'], 'messages' => $error['messages'], 'values' => $error['values'],
                ], $plan['errors']));
                $locked->forceFill([
                    'status' => 'completed', 'failed_rows' => count($plan['errors']), 'processed_rows' => $plan['total'],
                    'error_message' => null, 'completed_at' => now(),
                ])->save();
                $import->setRawAttributes($locked->getAttributes(), true);

                return true;
            }
            $locked->items()->createMany(array_map(static fn (array $item): array => [
                'product_id' => $item['product_id'], 'row_number' => $item['row'], 'name' => $item['name'],
                'payload' => $item['payload'], 'attribute_payload' => $item['attribute_payload'], 'status' => 'pending',
            ], $plan['items']));
            $locked->save();
            $import->setRawAttributes($locked->getAttributes(), true);

            return false;
        });
    }

    /** Process a bounded durable chunk and report whether no pending rows remain. */
    public function process(ProductImport $import): bool
    {
        $started = microtime(true);
        $count = 0;
        while ($count < self::CHUNK_SIZE && microtime(true) - $started < self::MAX_SECONDS) {
            $itemId = ProductImportItem::query()->where('product_import_id', $import->id)->where('status', 'pending')->orderBy('row_number')->value('id');
            if ($itemId === null) {
                break;
            }
            DB::transaction(function () use ($import, $itemId): void {
                $locked = ProductImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
                $item = ProductImportItem::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
                if ($item->status !== 'pending') {
                    return;
                }
                try {
                    $operation = $this->importService->applyPreparedRow($locked->user()->firstOrFail(), $item->product_id, $item->payload, $item->attribute_payload);
                    $operation === 'created' ? $locked->created_rows++ : $locked->updated_rows++;
                    $item->status = 'completed';
                    $item->save();
                } catch (ValidationException|UniqueConstraintViolationException $exception) {
                    $locked->rowErrors()->create([
                        'row_number' => $item->row_number, 'name' => $item->name,
                        'messages' => $exception instanceof ValidationException ? collect($exception->errors())->flatten()->values()->all() : ['Значение больше не уникально. Обновите экспорт и повторите импорт.'],
                        'values' => $item->payload,
                    ]);
                    $locked->failed_rows++;
                    $item->status = 'failed';
                    $item->save();
                }
                $locked->processed_rows++;
                $locked->last_processed_row = $item->row_number;
                $locked->save();
                $import->setRawAttributes($locked->getAttributes(), true);
            });
            $count++;
        }

        return ! ProductImportItem::query()->where('product_import_id', $import->id)->where('status', 'pending')->exists();
    }
}
