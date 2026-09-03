<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Support\ProductWorkbookSchema;
use DateTimeInterface;
use DOMDocument;
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

    /** @return array<string, string> */
    public function headers(Category $category): array
    {
        $headers = self::BASE_HEADERS;
        $attributes = $category->attributes()->with('options')->get();
        foreach ($attributes as $attribute) {
            $label = $attribute->name.($attribute->unit ? ' ('.$attribute->unit.')' : '');
            if ($attributes->where('name', $attribute->name)->count() > 1 || in_array($label, self::BASE_HEADERS, true)) {
                $label .= ' [#'.$attribute->id.']';
            }
            if ($attribute->pivot->is_required) {
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

    public function optionLabel(Attribute $attribute, mixed $option): string
    {
        return $attribute->options->where('label', $option->label)->count() > 1
            ? $option->label.' [#'.$option->id.']' : $option->label;
    }

    /** @param iterable<array<string, mixed>> $rows
     * @return array{path: string, name: string}
     */
    public function create(Category $category, iterable $rows = []): array
    {
        $headers = $this->headers($category);
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
                'SKU создаётся автоматически. Slug необязателен и автоматически формируется из названия.',
                'Значения списков выбирайте в ячейках. Новые значения создаются только на сайте при наличии прав.',
                'Множественный выбор: по одному значению в каждом пронумерованном столбце характеристики. Ненужные столбцы оставьте пустыми.',
                'Все значения списков проверяются повторно на сервере. Скрытый лист содержит справочники и ID.',
                'Не меняйте заголовки и категорию. Каждый товар создаётся отдельно; ошибки не отменяют другие строки.',
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

        return ['path' => $path, 'name' => 'products-'.$category->slug.'-template.xlsx'];
    }

    /** @return list<array{row: int, values: array<string, mixed>}> */
    public function read(Category $category, string $path): array
    {
        $headers = $this->headers($category);
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
                        $matchesCategory = ($values[0] ?? null) === 'AGAT_CATEGORY_TEMPLATE_V1' && (int) ($values[1] ?? 0) === $category->id;
                    }
                }
                if ($sheet->getName() !== 'Товары') {
                    continue;
                }
                $hasProducts = true;
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = array_map(fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value, $row->toArray());
                    if ($index === 1) {
                        if ($values !== array_values($headers)) {
                            throw ValidationException::withMessages(['file' => ['Столбцы не соответствуют выбранной категории. Скачайте актуальный шаблон; не изменяйте заголовки.']]);
                        }

                        continue;
                    }
                    if (collect($values)->every(fn ($value) => $value === null || $value === '')) {
                        continue;
                    }
                    if (count($entries) >= ProductImportService::MAX_ROWS || $index > ProductImportService::MAX_ROWS + 1) {
                        throw ValidationException::withMessages(['file' => ['Шаблон поддерживает максимум '.ProductImportService::MAX_ROWS.' товаров в строках 2–5001.']]);
                    }
                    if (count($values) > count($headers)) {
                        throw ValidationException::withMessages(['file' => ["Строка {$index}: значения выходят за пределы шаблона."]]);
                    }
                    $entries[] = ['row' => $index, 'values' => array_combine(array_keys($headers), array_pad($values, count($headers), null))];
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

    private function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn ($value) => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $values), $style);
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
    private function addValidation(string $path, array $headers, array $lists, array $listIndexes): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть шаблон Excel.');
        }
        try {
            $workbook = new DOMDocument;
            $workbook->loadXML($zip->getFromName('xl/workbook.xml'), LIBXML_NONET);
            $sheets = $workbook->getElementsByTagName('sheet');
            $sheets->item(1)->setAttribute('state', 'hidden');
            $definedNames = $workbook->createElement('definedNames');
            $sheet = new DOMDocument;
            $sheet->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'), LIBXML_NONET);
            $styles = new DOMDocument;
            $styles->loadXML($zip->getFromName('xl/styles.xml'), LIBXML_NONET);
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
            $textStyleIndex = $cellFormats->getElementsByTagName('xf')->length;
            $textFormat = $cellFormats->getElementsByTagName('xf')->item(0)->cloneNode(true);
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
                        $before->setAttribute('max', (string) ($target - 1));
                        $columnStyle->parentNode->insertBefore($before, $columnStyle);
                    }
                    if ($maximum > $target) {
                        $after = $columnStyle->cloneNode(true);
                        $after->setAttribute('min', (string) ($target + 1));
                        $columnStyle->parentNode->insertBefore($after, $columnStyle->nextSibling);
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
            $workbook->documentElement->appendChild($definedNames);
            // Every trailing worksheet element must follow dataValidations, including
            // the legacyDrawing emitted by OpenSpout even on sheets without comments.
            $before = null;
            foreach ($sheet->documentElement->childNodes as $child) {
                if (in_array($child->localName, ['hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks', 'colBreaks', 'customProperties', 'cellWatches', 'ignoredErrors', 'smartTags', 'drawing', 'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'webPublishItems', 'tableParts', 'extLst'], true)) {
                    $before = $child;
                    break;
                }
            }
            $sheet->documentElement->insertBefore($validations, $before);
            $zip->addFromString('xl/workbook.xml', $workbook->saveXML());
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet->saveXML());
            $zip->addFromString('xl/styles.xml', $styles->saveXML());
        } finally {
            $zip->close();
        }
    }
}
