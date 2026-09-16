<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Support\ProductWorkbookSchema;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

final class ProductImportTemplateWriter
{
    public function __construct(
        private readonly ProductImportTemplateSchema $schema,
        private readonly ProductImportTemplateValidationWriter $validation,
    ) {}

    /** @param iterable<array<string, mixed>> $rows
     * @return array{path: string, name: string}
     */
    public function create(Category $category, iterable $rows = [], bool $editing = false): array
    {
        $headers = $this->schema->headers($category, $editing);
        $lists = [
            'brand_name' => Brand::query()->orderBy('name')->get()->map(fn ($brand) => [$brand->name, $brand->id])->all(),
            'unit' => collect(ProductWorkbookSchema::UNIT_LABELS)->map(fn ($label, $value) => [$label, $value])->values()->all(),
            'is_active' => [['Да', 1], ['Нет', 0]],
            'is_on_sale' => [['Да', 1], ['Нет', 0]],
        ];
        foreach ($category->attributes()->with('options')->get() as $attribute) {
            if ($attribute->type === 'boolean') {
                $lists['attribute.'.$attribute->slug] = [['Да', 1], ['Нет', 0]];
            } elseif (in_array($attribute->type, ['select', 'multiselect'], true)) {
                $options = $attribute->options->map(fn ($option) => [$this->schema->optionLabel($attribute, $option), $option->id])->all();
                foreach (array_keys($headers) as $key) {
                    if ($key === 'attribute.'.$attribute->slug || str_starts_with($key, 'attribute.'.$attribute->slug.'.')) {
                        $lists[$key] = $options;
                    }
                }
            }
        }
        $lookupLists = [];
        $listIndexes = [];
        $listHashes = [];
        foreach ($lists as $key => $list) {
            $hash = hash('sha256', json_encode($list, JSON_THROW_ON_ERROR));
            if (! isset($listHashes[$hash])) {
                $listHashes[$hash] = count($lookupLists);
                $lookupLists[$key] = $list;
            }
            $listIndexes[$key] = $listHashes[$hash];
        }
        if (count($lookupLists) * 2 > 16384) {
            throw ValidationException::withMessages(['category_id' => ['Слишком много справочников для одного файла Excel.']]);
        }
        $path = tempnam(storage_path('app'), 'product-template-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать файл шаблона.');
        }
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Товары')->setSheetView((new SheetView)->setFreezeRow(2));
            // OpenSpout appends ranges rather than overriding earlier widths.
            for ($column = 1; $column <= count($headers); $column++) {
                $sheet->setColumnWidth(in_array($column, [1, 2, 5], true) ? 42 : 26, $column);
            }
            $writer->addRow($this->row(array_values($headers), (new Style)->setFontBold()->setFontColor('FFFFFF')
                ->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
                ->setBackgroundColor('23456B')->setShouldWrapText())->setHeight(45));
            foreach ($rows as $row) {
                $writer->addRow($this->row(array_map(fn ($key) => $row[$key] ?? null, array_keys($headers))));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Справочники');
            $writer->addRow($this->row(['AGAT_CATEGORY_TEMPLATE_V1', $category->id, $category->name]));
            $writer->addRow($this->row(['Лимит товаров', ProductImportService::MAX_ROWS]));
            $lookupHeaders = [];
            foreach ($lookupLists as $key => $list) {
                $lookupHeaders[] = $headers[$key];
                $lookupHeaders[] = 'ID';
            }
            $writer->addRow($this->row($lookupHeaders));
            $length = max(1, ...array_values(array_map('count', $lookupLists)));
            for ($index = 0; $index < $length; $index++) {
                $values = [];
                foreach ($lookupLists as $list) {
                    array_push($values, ...($list[$index] ?? [null, null]));
                }
                $writer->addRow($this->row($values));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Инструкция');
            $writer->getCurrentSheet()->setColumnWidth(110, 1);
            foreach ([
                'Категория: '.$category->name.'. Максимум '.ProductImportService::MAX_ROWS.' товаров (строки 2–5001).',
                'Обязательные поля отмечены *. Остаток по умолчанию 0, Активность и Распродажа — Нет.',
                'Характеристики со звёздочкой обязательны для активного товара. Неактивный товар можно сохранить с неполными характеристиками.',
                $editing ? 'SKU определяет редактируемый товар. Не меняйте SKU и не добавляйте новые строки: товары обновляются только в выбранной категории.' : 'SKU создаётся автоматически. Slug необязателен и автоматически формируется из названия.',
                'Значения списков выбирайте в ячейках. Новые значения создаются только на сайте при наличии прав.',
                'Множественный выбор: по одному значению в каждом пронумерованном столбце характеристики. Ненужные столбцы оставьте пустыми.',
                'Все значения списков проверяются повторно на сервере. Скрытый лист содержит справочники и ID.',
                'Не меняйте заголовки и категорию. Каждый товар обрабатывается отдельно; ошибки не отменяют другие строки.',
                'При ошибке скачайте файл ошибочных строк, исправьте его и загрузите повторно.',
            ] as $instruction) {
                $writer->addRow($this->row([$instruction], (new Style)->setShouldWrapText())->setHeight(32));
            }
            $writer->close();
            $this->validation->apply($path, $headers, $lists, $listIndexes);
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => 'products-'.$category->slug.($editing ? '-edit' : '-template').'.xlsx'];
    }

    /** @param list<mixed> $values */
    private function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (mixed $value): Cell => is_string($value) ? new StringCell($value, null) : Cell::fromValue($this->cellScalar($value)), $values), $style);
    }

    private function cellScalar(mixed $value): bool|DateTimeInterface|\DateInterval|float|int|string|null
    {
        if (is_bool($value) || $value instanceof DateTimeInterface || $value instanceof \DateInterval || is_float($value) || is_int($value) || is_string($value) || $value === null) {
            return $value;
        }

        return is_object($value) && method_exists($value, '__toString') ? (string) $value : '';
    }
}
