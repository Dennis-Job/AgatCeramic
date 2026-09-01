<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Queries\ProductQuery;
use Illuminate\Support\LazyCollection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class ProductExportService
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const BASE_HEADERS = [
        'id', 'sku', 'article_number', 'barcode', 'name', 'slug', 'description',
        'category_id', 'category_slug', 'category_name', 'brand_id', 'brand_slug', 'brand_name',
        'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale',
        'primary_image_url', 'product_group_id', 'product_group_code', 'product_group_name',
        'created_at', 'updated_at',
    ];

    public function __construct(private readonly ProductQuery $productQuery) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{path: string, name: string}
     */
    public function create(array $filters): array
    {
        $attributes = Attribute::query()
            ->leftJoin('attribute_groups as export_groups', 'export_groups.id', '=', 'attributes.attribute_group_id')
            ->select('attributes.*')
            ->orderByRaw('CASE WHEN export_groups.id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('export_groups.sort_order')
            ->orderBy('export_groups.name')
            ->orderBy('export_groups.id')
            ->orderBy('attributes.sort_order')
            ->orderBy('attributes.name')
            ->orderBy('attributes.id')
            ->get();
        $path = tempnam(storage_path('app'), 'product-export-');

        if ($path === false) {
            throw new RuntimeException('Не удалось создать временный файл экспорта.');
        }

        $writer = new Writer;

        try {
            $writer->openToFile($path);
            $writer->getCurrentSheet()
                ->setName('Товары')
                ->setSheetView((new SheetView)->setFreezeRow(2));
            $writer->addRow(Row::fromValues([
                ...self::BASE_HEADERS,
                ...$attributes->map(fn (Attribute $attribute): string => 'attribute.'.$attribute->slug)->all(),
            ], (new Style)->setFontBold()));

            /** @var LazyCollection<int, Product> $products */
            $products = $this->productQuery->filtered($filters)
                ->with(['category', 'brand', 'primaryImage', 'groupMembership.group', 'attributeValues'])
                ->lazy(500);

            foreach ($products as $product) {
                $values = $product->attributeValues->keyBy('attribute_id');
                $writer->addRow(Row::fromValues([
                    $product->id,
                    (string) $product->sku,
                    $product->article_number,
                    $product->barcode === null ? null : (string) $product->barcode,
                    $product->name,
                    $product->slug,
                    $product->description,
                    $product->category_id,
                    $product->category->slug,
                    $product->category->name,
                    $product->brand_id,
                    $product->brand?->slug,
                    $product->brand?->name,
                    $product->unit,
                    (float) $product->price,
                    $product->old_price === null ? null : (float) $product->old_price,
                    $product->stock_quantity,
                    $product->is_active,
                    $product->is_on_sale,
                    $product->primaryImage?->url,
                    $product->groupMembership?->group?->id,
                    $product->groupMembership?->group?->code,
                    $product->groupMembership?->group?->name,
                    $product->created_at?->toAtomString(),
                    $product->updated_at?->toAtomString(),
                    ...$attributes->map(fn (Attribute $attribute): bool|float|int|string|null => $this->cellValue(
                        $values->get($attribute->id)?->value
                    ))->all(),
                ]));
            }

            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
                // Preserve the original export failure while releasing resources when possible.
            }
            @unlink($path);
            throw $exception;
        }

        return [
            'path' => $path,
            'name' => 'products-'.now()->format('Y-m-d-His').'.xlsx',
        ];
    }

    private function cellValue(mixed $value): bool|float|int|string|null
    {
        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value) || is_float($value) || is_int($value) || is_string($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }
}
