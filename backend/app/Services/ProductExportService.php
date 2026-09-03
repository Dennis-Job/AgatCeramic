<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Queries\ProductQuery;
use App\Support\ProductWorkbookSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

class ProductExportService
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private readonly ProductQuery $productQuery) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{path: string, name: string}
     */
    public function create(array $filters): array
    {
        $imageCount = $this->productQuery->maximumImageCount($filters);
        $imageHeaders = [];
        for ($number = 1; $number <= $imageCount; $number++) {
            $imageHeaders[] = 'Изображение '.$number;
        }
        $attributes = Attribute::query()
            ->leftJoin('attribute_groups as export_groups', 'export_groups.id', '=', 'attributes.attribute_group_id')
            ->select('attributes.*')
            ->with('options')
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
            $headers = [
                ...array_values(ProductWorkbookSchema::MANAGER_HEADERS),
                ...$attributes->map(fn (Attribute $attribute): string => $attribute->name.($attribute->unit ? ' ('.$attribute->unit.')' : ''))->all(),
                ...$imageHeaders,
            ];
            $imageStart = count($headers) - $imageCount;
            $imageWidths = $imageCount === 0 ? [] : array_fill($imageStart, $imageCount, 48);
            $managerSheet = $writer->getCurrentSheet();
            $this->prepareSheet($writer, $managerSheet, 'Товары', $headers);
            $managerSheet->setColumnWidth(48, 4, 5);
            $managerSheet->setColumnWidth(26, 6, 7, 15);
            $managerSheet->setColumnWidth(22, 16, 17);
            if ($imageCount > 0) {
                $managerSheet->setColumnWidthForRange(48, $imageStart + 1, count($headers));
            }
            $seoSheet = $writer->addNewSheetAndMakeItCurrent();
            $this->prepareSheet($writer, $seoSheet, 'SEO товаров', [
                'SKU', 'Название', 'URL товара (slug)', 'Категория', 'URL категории (slug)', 'Бренд', 'URL бренда (slug)',
            ]);
            $seoSheet->setColumnWidth(42, 2, 3, 5, 7);
            $attributeSheet = $writer->addNewSheetAndMakeItCurrent();
            $this->prepareSheet($writer, $attributeSheet, 'SEO характеристик', ['Название', 'Единица измерения', 'URL характеристики (slug)']);
            $attributeSheet->setColumnWidth(36, 1, 3);
            foreach ($attributes as $attribute) {
                $writer->addRow($this->row([$attribute->name, $attribute->unit, $attribute->slug], widths: [0 => 36, 2 => 36]));
            }
            $attributeSheet->setAutoFilter(new AutoFilter(0, 1, 2, $attributes->count() + 1));
            $rowNumber = 1;
            $moneyStyle = (new Style)->setFormat('#,##0.00');
            $dateStyle = (new Style)->setFormat('dd.mm.yyyy hh:mm');

            /** @var LazyCollection<int, Product> $products */
            $products = $this->productQuery->filtered($filters)
                ->with(['category', 'brand', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'), 'groupMembership.group', 'attributeValues'])
                ->lazy(500);

            foreach ($products as $product) {
                $writer->setCurrentSheet($managerSheet);
                $values = $product->attributeValues->keyBy('attribute_id');
                $writer->addRow($this->row([
                    (string) $product->sku,
                    $product->article_number,
                    $product->barcode === null ? null : (string) $product->barcode,
                    $product->name,
                    $product->description,
                    $product->category->name,
                    $product->brand?->name,
                    ProductWorkbookSchema::UNIT_LABELS[$product->unit] ?? $product->unit,
                    (float) $product->price,
                    $product->old_price === null ? null : (float) $product->old_price,
                    $product->stock_quantity,
                    $product->is_active ? 'Да' : 'Нет',
                    $product->is_on_sale ? 'Да' : 'Нет',
                    $product->groupMembership?->group?->code,
                    $product->groupMembership?->group?->name,
                    $product->created_at,
                    $product->updated_at,
                    ...$attributes->map(fn (Attribute $attribute): float|int|string|null => $this->cellValue(
                        $attribute,
                        $values->get($attribute->id)?->value
                    ))->all(),
                    ...array_pad($product->images->map(fn (ProductImage $image): string => url(Storage::disk($image->disk)->url($image->path)))->all(), $imageCount, null),
                ], (new Style)->setShouldWrapText()->setBackgroundColor($rowNumber % 2 === 1 ? 'F2F6FC' : 'FFFFFF'), [
                    8 => $moneyStyle, 9 => $moneyStyle, 15 => $dateStyle, 16 => $dateStyle,
                ], [3 => 48, 4 => 48, 5 => 26, 6 => 26, 14 => 26, 15 => 22, 16 => 22] + $imageWidths));
                $writer->setCurrentSheet($seoSheet);
                $writer->addRow($this->row([
                    (string) $product->sku, $product->name, $product->slug,
                    $product->category->name, $product->category->slug,
                    $product->brand?->name, $product->brand?->slug,
                ], widths: [1 => 42, 2 => 42, 4 => 42, 6 => 42]));
                $rowNumber++;
            }
            $managerSheet->setAutoFilter(new AutoFilter(0, 1, count($headers) - 1, $rowNumber));
            $seoSheet->setAutoFilter(new AutoFilter(0, 1, 6, $rowNumber));
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

    /** @param list<string> $headers */
    private function prepareSheet(Writer $writer, Sheet $sheet, string $name, array $headers): void
    {
        $sheet->setName($name)->setSheetView((new SheetView)->setFreezeRow(2)->setFreezeColumn('B'));
        $sheet->setColumnWidthForRange(20, 1, count($headers));
        $writer->addRow($this->row($headers, (new Style)->setFontBold()->setFontColor('FFFFFF')
            ->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBackgroundColor('23456B')->setShouldWrapText())->setHeight(42));
    }

    /**
     * @param  list<mixed>  $values
     * @param  array<int, Style>  $columnStyles
     * @param  array<int, int>  $widths
     */
    private function row(array $values, ?Style $style = null, array $columnStyles = [], array $widths = []): Row
    {
        $cells = [];
        $lines = 1;
        foreach ($values as $index => $value) {
            $cellStyle = $columnStyles[$index] ?? null;
            $cells[] = is_string($value) ? new StringCell($value, $cellStyle) : Cell::fromValue($value, $cellStyle);
            if (is_string($value)) {
                $cellLines = 0;
                foreach (explode("\n", $value) as $line) {
                    $cellLines += max(1, (int) ceil(mb_strwidth($line) / (($widths[$index] ?? 20) - 4)));
                }
                $lines = max($lines, $cellLines);
            }
        }

        return (new Row($cells, $style ?? (new Style)->setShouldWrapText()))->setHeight(min(409, max(24, $lines * 16 + 8)));
    }

    private function cellValue(Attribute $attribute, mixed $value): float|int|string|null
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }
        if ($attribute->type === 'select' || $attribute->type === 'multiselect') {
            $labels = $attribute->options->pluck('label', 'value');
            $display = array_map(fn (mixed $option): string => $labels->get((string) $option) ?? (string) $option, is_array($value) ? $value : [$value]);
            if ($attribute->type === 'multiselect') {
                $display = array_map(static fn (string $label): string => strpbrk($label, ';"') === false ? $label : '"'.str_replace('"', '""', $label).'"', $display);
            }

            return implode('; ', $display);
        }
        if (is_float($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        return (string) $value;
    }
}
