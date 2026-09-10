<?php

namespace App\Services;

use App\Models\ProductImport;
use App\Models\ProductImportItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type ProductPayload from ProductImportService
 * @phpstan-import-type AttributePayload from ProductImportService
 */
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
                    $operation = $this->importService->applyPreparedRow($locked->user()->firstOrFail(), $item->product_id, $this->itemPayload($item), $this->itemAttributePayload($item));
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

    /** @return ProductPayload */
    private function itemPayload(ProductImportItem $item): array
    {
        $payload = $this->jsonObject($item->getRawOriginal('payload'));

        return [
            'category_id' => $this->requiredInt($payload, 'category_id'),
            'brand_id' => $this->nullableInt($payload, 'brand_id'),
            'name' => $this->requiredString($payload, 'name'),
            'slug' => $this->requiredString($payload, 'slug'),
            'description' => $this->nullableString($payload, 'description'),
            'article_number' => $this->nullableString($payload, 'article_number'),
            'barcode' => $this->nullableString($payload, 'barcode'),
            'unit' => $this->requiredString($payload, 'unit'),
            'price' => $this->requiredString($payload, 'price'),
            'old_price' => $this->nullableString($payload, 'old_price'),
            'stock_quantity' => $this->requiredInt($payload, 'stock_quantity'),
            'is_active' => $this->requiredBool($payload, 'is_active'),
            'is_on_sale' => $this->requiredBool($payload, 'is_on_sale'),
        ];
    }

    /** @return AttributePayload */
    private function itemAttributePayload(ProductImportItem $item): array
    {
        $payload = json_decode($this->rawJson($item->getRawOriginal('attribute_payload')), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ! array_is_list($payload)) {
            throw new \LogicException('Durable import attribute payload is invalid.');
        }

        $attributes = [];
        foreach ($payload as $attribute) {
            if (! is_array($attribute)) {
                throw new \LogicException('Durable import attribute payload is invalid.');
            }
            $attribute = $this->stringKeyedArray($attribute);
            $attributes[] = ['attribute_id' => $this->requiredInt($attribute, 'attribute_id'), 'value' => $attribute['value'] ?? null];
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $json): array
    {
        $value = json_decode($this->rawJson($json), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value) || array_is_list($value)) {
            throw new \LogicException('Durable import payload is invalid.');
        }

        return $this->stringKeyedArray($value);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $value): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new \LogicException('Durable import payload has invalid keys.');
            }
            $result[$key] = $item;
        }

        return $result;
    }

    private function rawJson(mixed $value): string
    {
        if (! is_string($value)) {
            throw new \LogicException('Durable import payload storage is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function requiredInt(array $payload, string $field): int
    {
        $value = $payload[$field] ?? null;
        if (! is_int($value)) {
            throw new \LogicException("Durable import field {$field} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function nullableInt(array $payload, string $field): ?int
    {
        $value = $payload[$field] ?? null;
        if ($value !== null && ! is_int($value)) {
            throw new \LogicException("Durable import field {$field} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;
        if (! is_string($value)) {
            throw new \LogicException("Durable import field {$field} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function nullableString(array $payload, string $field): ?string
    {
        $value = $payload[$field] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new \LogicException("Durable import field {$field} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function requiredBool(array $payload, string $field): bool
    {
        $value = $payload[$field] ?? null;
        if (! is_bool($value)) {
            throw new \LogicException("Durable import field {$field} is invalid.");
        }

        return $value;
    }
}
