<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Support\ProductWorkbookSchema;
use Illuminate\Support\Facades\DB;

/**
 * Stable product-import facade. Workbook I/O, planning and mutation are delegated to focused services.
 *
 * @phpstan-type ProductPayload array{category_id: int, brand_id: ?int, name: string, slug: string, description: ?string, article_number: ?string, barcode: ?string, unit: string, price: string, old_price: ?string, stock_quantity: int, is_active: bool, is_on_sale: bool}
 * @phpstan-type AttributePayload list<array{attribute_id: int, value: mixed}>
 * @phpstan-type InspectionError array{row: int, name: ?string, messages: list<string>, values: array<string, mixed>}
 */
class ProductImportService
{
    public const MAX_ROWS = ProductWorkbookSchema::MAX_ROWS;

    public function __construct(
        private readonly ProductWorkbookReader $reader,
        private readonly ProductImportRowMapper $mapper,
        private readonly ProductImportPlanBuilder $plans,
        private readonly ProductImportExecutor $executor,
        private readonly CategoryProductRowImporter $categoryRows,
    ) {}

    /** @param array<string, mixed> $values */
    public function createTemplateRow(User $actor, Category $category, array $values, int $row, bool $editing = false): string
    {
        return $this->categoryRows->apply($actor, $category, $values, $row, $editing);
    }

    /** @return array{created: int, updated: int, processed: int} */
    public function import(User $actor, string $path): array
    {
        $workbook = $this->reader->read($path);
        $attributes = $this->mapper->attributesFor($workbook['headers']);

        return DB::transaction(function () use ($actor, $workbook, $attributes): array {
            $prepared = $this->plans->build($workbook['headers'], $workbook['rows'], $attributes, $workbook['seoProducts'], $workbook['localized']);
            $created = 0;
            $updated = 0;
            foreach ($prepared as $entry) {
                $operation = $this->executor->apply($actor, $entry['product']?->id, $entry['payload'], $entry['attributePayload']);
                $operation === 'created' ? $created++ : $updated++;
            }

            return ['created' => $created, 'updated' => $updated, 'processed' => $created + $updated];
        });
    }

    /** @return array{total: int, errors: list<InspectionError>} */
    public function inspect(string $path): array
    {
        $workbook = $this->reader->read($path);
        $errors = [];
        $this->plans->build($workbook['headers'], $workbook['rows'], $this->mapper->attributesFor($workbook['headers']), $workbook['seoProducts'], $workbook['localized'], $errors);

        return ['total' => count($workbook['rows']), 'errors' => $errors ?? []];
    }

    /** @return array{total: int, errors: list<InspectionError>, items: list<array{row: int, name: ?string, product_id: ?int, payload: ProductPayload, attribute_payload: AttributePayload}>} */
    public function prepareForQueue(string $path): array
    {
        $workbook = $this->reader->read($path);
        $errors = [];
        $prepared = $this->plans->build($workbook['headers'], $workbook['rows'], $this->mapper->attributesFor($workbook['headers']), $workbook['seoProducts'], $workbook['localized'], $errors);

        return [
            'total' => count($workbook['rows']),
            'errors' => $errors ?? [],
            'items' => array_map(static fn (array $entry): array => [
                'row' => $entry['row'], 'name' => $entry['name'], 'product_id' => $entry['product']?->id,
                'payload' => $entry['payload'], 'attribute_payload' => $entry['attributePayload'],
            ], $prepared),
        ];
    }

    /** @param ProductPayload $payload
     * @param  AttributePayload  $attributePayload
     */
    public function applyPreparedRow(User $actor, ?int $productId, array $payload, array $attributePayload): string
    {
        return $this->executor->apply($actor, $productId, $payload, $attributePayload);
    }
}
