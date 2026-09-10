<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductWorkbookSchema;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;
use ZipArchive;

class ProductImportTemplateService
{
    public const BASE_HEADERS = [
        'name' => 'Название *', 'slug' => 'Slug (необязательно)', 'article_number' => 'Артикул',
        'barcode' => 'Штрихкод', 'description' => 'Описание', 'brand_name' => 'Бренд',
        'unit' => 'Единица продажи *', 'price' => 'Цена *', 'old_price' => 'Старая цена',
        'stock_quantity' => 'Остаток', 'is_active' => 'Активность', 'is_on_sale' => 'Распродажа',
    ];

    public const EDIT_HEADERS = ['sku' => 'SKU'];

    /** @return array<string, string> */
    public function headers(Category $category, bool $editing = false): array
    {
        $headers = $editing ? self::EDIT_HEADERS + self::BASE_HEADERS : self::BASE_HEADERS;
        $attributes = $category->attributes()->with('options')->get();
        foreach ($attributes as $attribute) {
            $label = $attribute->name.($attribute->unit ? ' ('.$attribute->unit.')' : '');
            if ($attributes->where('name', $attribute->name)->count() > 1 || in_array($label, self::BASE_HEADERS, true)) {
                $label .= ' [#'.$attribute->id.']';
            }
            if (data_get($attribute, 'pivot.is_required')) {
                $label .= ' *';
            }
            $count = $attribute->type === 'multiselect' ? max(1, $attribute->options->count()) : 1;
            for ($slot = 1; $slot <= $count; $slot++) {
                $key = 'attribute.'.$attribute->slug.($attribute->type === 'multiselect' ? '.'.$slot : '');
                $headers[$key] = $label.($attribute->type === 'multiselect' ? ' — '.$slot : '');
            }
        }
        if (count($headers) > 16384) {
            throw ValidationException::withMessages(['category_id' => ['Слишком много столбцов для формата Excel. Уменьшите число значений множественного выбора.']]);
        }

        return $headers;
    }

    /** @return list<array<string, mixed>> */
    public function editingRows(Category $category): array
    {
        $attributes = $category->attributes()->with('options')->get()->keyBy('id');

        return array_values(Product::query()->where('category_id', $category->id)->with(['brand', 'attributeValues'])->orderBy('sku')->get()
            ->map(function (Product $product) use ($attributes): array {
                $row = [
                    'sku' => $product->sku, 'name' => $product->name, 'slug' => $product->slug,
                    'article_number' => $product->article_number, 'barcode' => $product->barcode,
                    'description' => $product->description, 'brand_name' => $product->brand?->name,
                    'unit' => ProductWorkbookSchema::UNIT_LABELS[(string) $product->unit] ?? (string) $product->unit,
                    'price' => $product->price, 'old_price' => $product->old_price,
                    'stock_quantity' => $product->stock_quantity, 'is_active' => $product->is_active ? 'Да' : 'Нет',
                    'is_on_sale' => $product->is_on_sale ? 'Да' : 'Нет',
                ];
                foreach ($product->attributeValues as $value) {
                    $attribute = $attributes->get($value->attribute_id);
                    if ($attribute === null) {
                        continue;
                    }
                    $key = 'attribute.'.$attribute->slug;
                    if ($attribute->type === 'boolean') {
                        $row[$key] = $value->value ? 'Да' : 'Нет';
                    } elseif ($attribute->type === 'select') {
                        $option = $attribute->options->firstWhere('value', $value->value);
                        $row[$key] = $option === null ? null : $this->optionLabel($attribute, $option);
                    } elseif ($attribute->type === 'multiselect') {
                        foreach ((array) $value->value as $index => $optionValue) {
                            $option = $attribute->options->firstWhere('value', $optionValue);
                            $row[$key.'.'.($index + 1)] = $option === null ? null : $this->optionLabel($attribute, $option);
                        }
                    } else {
                        $row[$key] = $value->value;
                    }
                }

                return $row;
            })->all());
    }

    public function optionLabel(Attribute $attribute, AttributeOption $option): string
    {
        return $attribute->options->where('label', $option->label)->count() > 1
            ? $option->label.' [#'.$option->id.']' : $option->label;
    }

    /** @param iterable<array<string, mixed>> $rows
     * @return array{path: string, name: string}
     */
    public function create(Category $category, iterable $rows = [], bool $editing = false): array
    {
        $headers = $this->headers($category, $editing);
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
                $options = $attribute->options->map(fn ($option) => [$this->optionLabel($attribute, $option), $option->id])->all();
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
            $this->addValidation($path, $headers, $lists, $listIndexes);
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

    /** @return list<array{row: int, values: array<string, mixed>}> */
    public function read(Category $category, string $path): array
    {
        $headers = $this->headers($category);
        $editingHeaders = $this->headers($category, true);
        $editing = false;
        $readerOptions = new ReaderOptions;
        $readerOptions->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($readerOptions);
        $entries = [];
        $matchesCategory = false;
        $hasProducts = false;
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() === 'Справочники') {
                    foreach ($sheet->getRowIterator() as $index => $row) {
                        if ($index !== 1) {
                            continue;
                        }
                        $values = $row->toArray();
                        $matchesCategory = ($values[0] ?? null) === 'AGAT_CATEGORY_TEMPLATE_V1' && $this->integerValue($values[1] ?? 0) === $category->id;
                    }
                }
                if ($sheet->getName() !== 'Товары') {
                    continue;
                }
                $hasProducts = true;
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = array_map(fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value, $row->toArray());
                    if ($index === 1) {
                        if ($values === array_values($editingHeaders)) {
                            $headers = $editingHeaders;
                            $editing = true;
                        } elseif ($values !== array_values($headers)) {
                            throw ValidationException::withMessages(['file' => ['Столбцы не соответствуют выбранной категории. Скачайте актуальный шаблон; не изменяйте заголовки.']]);
                        }

                        continue;
                    }
                    if (collect($values)->every(fn ($value) => $value === null || $value === '')) {
                        continue;
                    }
                    if (! is_int($index) || count($entries) >= ProductImportService::MAX_ROWS || $index > ProductImportService::MAX_ROWS + 1) {
                        throw ValidationException::withMessages(['file' => ['Шаблон поддерживает максимум '.ProductImportService::MAX_ROWS.' товаров в строках 2–5001.']]);
                    }
                    if (count($values) > count($headers)) {
                        throw ValidationException::withMessages(['file' => ["Строка {$index}: значения выходят за пределы шаблона."]]);
                    }
                    $entries[] = ['row' => $index, 'editing' => $editing, 'values' => array_combine(array_keys($headers), array_pad($values, count($headers), null))];
                }
            }
            if (! $matchesCategory || ! $hasProducts) {
                throw ValidationException::withMessages(['file' => ['Файл не является шаблоном выбранной категории. Скачайте шаблон ещё раз.']]);
            }
            if ($entries === []) {
                throw ValidationException::withMessages(['file' => ['XLSX-файл не содержит товаров для импорта.']]);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => ['Не удалось прочитать XLSX-файл. Проверьте, что файл не повреждён.']]);
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
            }
            // OpenSpout row iterators hold callbacks; release their XML streams on early validation failure too.
            unset($sheet, $reader);
            gc_collect_cycles();
        }

        return $entries;
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

    private function integerValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private function column(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + $number % 26).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    /** OpenSpout streams cells; this adds standard OOXML list validation without materializing 5000 empty rows. */
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, array<int, array{0: mixed, 1: mixed}>>  $lists
     * @param  array<string, int>  $listIndexes
     */
    private function addValidation(string $path, array $headers, array $lists, array $listIndexes): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть шаблон Excel.');
        }
        try {
            $workbook = new DOMDocument;
            $workbookSource = $zip->getFromName('xl/workbook.xml');
            $sheetSource = $zip->getFromName('xl/worksheets/sheet1.xml');
            $stylesSource = $zip->getFromName('xl/styles.xml');
            if (! is_string($workbookSource) || ! $workbook->loadXML($workbookSource, LIBXML_NONET) || $workbook->documentElement === null) {
                throw new RuntimeException('Не удалось прочитать структуру шаблона Excel.');
            }
            $workbookRoot = $workbook->documentElement;
            $sheets = $workbook->getElementsByTagName('sheet');
            $lookupSheet = $sheets->item(1);
            if (! $lookupSheet instanceof DOMElement) {
                throw new RuntimeException('Не удалось прочитать лист справочников шаблона Excel.');
            }
            $lookupSheet->setAttribute('state', 'hidden');
            $definedNames = $workbook->createElement('definedNames');
            $sheet = new DOMDocument;
            if (! is_string($sheetSource) || ! $sheet->loadXML($sheetSource, LIBXML_NONET) || $sheet->documentElement === null) {
                throw new RuntimeException('Не удалось прочитать лист товаров шаблона Excel.');
            }
            $sheetRoot = $sheet->documentElement;
            $styles = new DOMDocument;
            if (! is_string($stylesSource) || ! $styles->loadXML($stylesSource, LIBXML_NONET)) {
                throw new RuntimeException('Не удалось прочитать стили шаблона Excel.');
            }
            // Normalize OpenSpout styles to the SpreadsheetML schema used by Excel.
            foreach ($styles->getElementsByTagName('fgColor') as $color) {
                if (strlen($color->getAttribute('rgb')) === 6) {
                    $color->setAttribute('rgb', 'FF'.$color->getAttribute('rgb'));
                }
            }
            foreach ($styles->getElementsByTagName('font') as $font) {
                $children = iterator_to_array($font->childNodes);
                foreach (['b', 'i', 'strike', 'condense', 'extend', 'outline', 'shadow', 'u', 'vertAlign', 'sz', 'color', 'name', 'family', 'charset', 'scheme'] as $name) {
                    foreach ($children as $child) {
                        if ($child->localName === $name) {
                            $font->appendChild($child);
                        }
                    }
                }
            }
            $cellFormats = $styles->getElementsByTagName('cellXfs')->item(0);
            if (! $cellFormats instanceof DOMElement) {
                throw new RuntimeException('Не удалось прочитать форматы ячеек шаблона Excel.');
            }
            $textStyleIndex = $cellFormats->getElementsByTagName('xf')->length;
            $baseFormat = $cellFormats->getElementsByTagName('xf')->item(0);
            if (! $baseFormat instanceof DOMNode) {
                throw new RuntimeException('Не удалось прочитать текстовый формат шаблона Excel.');
            }
            $textFormat = $baseFormat->cloneNode(true);
            if (! $textFormat instanceof DOMElement) {
                throw new RuntimeException('Не удалось скопировать текстовый формат шаблона Excel.');
            }
            $textFormat->setAttribute('numFmtId', '49'); // Built-in Excel text format (@).
            $textFormat->setAttribute('applyNumberFormat', '1');
            $cellFormats->appendChild($textFormat);
            $cellFormats->setAttribute('count', (string) ($textStyleIndex + 1));
            foreach (['article_number', 'barcode'] as $key) {
                $target = array_search($key, array_keys($headers), true) + 1;
                foreach ($sheet->getElementsByTagName('col') as $columnStyle) {
                    $minimum = (int) $columnStyle->getAttribute('min');
                    $maximum = (int) $columnStyle->getAttribute('max');
                    if ($minimum > $target || $maximum < $target) {
                        continue;
                    }
                    if ($minimum < $target) {
                        $before = $columnStyle->cloneNode(true);
                        if ($before instanceof DOMElement && $columnStyle->parentNode !== null) {
                            $before->setAttribute('max', (string) ($target - 1));
                            $columnStyle->parentNode->insertBefore($before, $columnStyle);
                        }
                    }
                    if ($maximum > $target) {
                        $after = $columnStyle->cloneNode(true);
                        if ($after instanceof DOMElement && $columnStyle->parentNode !== null) {
                            $after->setAttribute('min', (string) ($target + 1));
                            $columnStyle->parentNode->insertBefore($after, $columnStyle->nextSibling);
                        }
                    }
                    $columnStyle->setAttribute('min', (string) $target);
                    $columnStyle->setAttribute('max', (string) $target);
                    $columnStyle->setAttribute('style', (string) $textStyleIndex);
                    break;
                }
            }
            $validations = $sheet->createElement('dataValidations');
            $validations->setAttribute('count', (string) count($lists));
            $index = 0;
            foreach ($lists as $key => $list) {
                $name = 'AgatList'.($index + 1);
                $column = $this->column($listIndexes[$key] * 2 + 1);
                $defined = $workbook->createElement('definedName');
                $defined->setAttribute('name', $name);
                $defined->appendChild($workbook->createTextNode("'Справочники'!\${$column}\$4:\${$column}\$".(max(1, count($list)) + 3)));
                $definedNames->appendChild($defined);
                $target = $this->column(array_search($key, array_keys($headers), true) + 1);
                $validation = $sheet->createElement('dataValidation');
                foreach (['type' => 'list', 'errorStyle' => 'stop', 'allowBlank' => '1', 'showErrorMessage' => '1', 'showInputMessage' => '1', 'showDropDown' => '0', 'sqref' => $target.'2:'.$target.(ProductImportService::MAX_ROWS + 1), 'errorTitle' => 'Выберите значение из списка', 'error' => 'Допустимы только значения справочника.', 'promptTitle' => 'Значение из справочника', 'prompt' => 'Выберите значение в выпадающем списке.'] as $attribute => $value) {
                    $validation->setAttribute($attribute, $value);
                }
                $validation->appendChild($sheet->createElement('formula1', $name));
                $validations->appendChild($validation);
                $index++;
            }
            $workbookRoot->appendChild($definedNames);
            // Every trailing worksheet element must follow dataValidations, including
            // the legacyDrawing emitted by OpenSpout even on sheets without comments.
            $before = null;
            foreach ($sheetRoot->childNodes as $child) {
                if (in_array($child->localName, ['hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks', 'colBreaks', 'customProperties', 'cellWatches', 'ignoredErrors', 'smartTags', 'drawing', 'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'webPublishItems', 'tableParts', 'extLst'], true)) {
                    $before = $child;
                    break;
                }
            }
            $sheetRoot->insertBefore($validations, $before);
            $workbookXml = $workbook->saveXML();
            $sheetXml = $sheet->saveXML();
            $stylesXml = $styles->saveXML();
            if (! is_string($workbookXml) || ! is_string($sheetXml) || ! is_string($stylesXml)) {
                throw new RuntimeException('Не удалось сохранить структуру шаблона Excel.');
            }
            $zip->addFromString('xl/workbook.xml', $workbookXml);
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
            $zip->addFromString('xl/styles.xml', $stylesXml);
        } finally {
            $zip->close();
        }
    }
}
