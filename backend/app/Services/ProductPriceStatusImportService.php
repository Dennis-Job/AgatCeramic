<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportItem;
use DateTimeInterface;
use DOMDocument;
use Illuminate\Support\Facades\DB;
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

/** A deliberately narrow import: no category, characteristics, names, or stock can be changed here. */
class ProductPriceStatusImportService
{
    public const MAX_ROWS_PER_SHEET = 5000;

    /** @var array<string, list<string>> */
    private const HEADERS = [
        'Цены' => ['SKU *', 'Цена *', 'Старая цена'],
        'Активность' => ['SKU *', 'Активность *'],
        'Распродажа' => ['SKU *', 'Распродажа *'],
    ];

    /** @return array{path: string, name: string} */
    public function createTemplate(): array
    {
        $path = tempnam(storage_path('app'), 'price-status-template-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать файл шаблона.');
        }
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            foreach (self::HEADERS as $sheetName => $headers) {
                $sheet = $writer->getCurrentSheet();
                $sheet->setName($sheetName)->setSheetView((new SheetView)->setFreezeRow(2));
                foreach (array_keys($headers) as $index) {
                    $sheet->setColumnWidth($index === 0 ? 26 : 20, $index + 1);
                }
                $writer->addRow($this->row($headers, $this->headerStyle())->setHeight(36));
                if ($sheetName !== 'Распродажа') {
                    $writer->addNewSheetAndMakeItCurrent();
                }
            }
            $writer->close();
            $this->addBooleanValidation($path);
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => 'product-price-status-template.xlsx'];
    }

    /** Creates durable, validated work items. Invalid rows do not prevent unrelated valid rows from updating. */
    public function initialize(ProductImport $import, string $path): void
    {
        $entries = $this->read($path);
        $seen = [];
        $sequence = 0;
        foreach ($entries as $entry) {
            $sequence++;
            $messages = $this->validate($seen, $entry);
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
                    'messages' => array_map(fn (string $message) => "Лист «{$entry['sheet']}», строка {$entry['row']}: {$message}", $messages),
                    'values' => $entry,
                ]);

                continue;
            }
            ProductImportItem::query()->create([
                'product_import_id' => $import->id, 'product_id' => $product->id, 'row_number' => $sequence,
                'name' => $entry['sku'], 'payload' => ['updates' => $entry['updates'], 'sheet' => $entry['sheet'], 'source_row' => $entry['row']],
                'attribute_payload' => [], 'status' => 'pending',
            ]);
        }
        $import->forceFill(['total_rows' => count($entries), 'failed_rows' => $import->rowErrors()->count(), 'processed_rows' => $import->rowErrors()->count()])->save();
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
                $operation = $locked->payload;
                try {
                    $product = Product::query()->find($locked->product_id);
                    if ($product === null) {
                        throw ValidationException::withMessages(['sku' => ['Товар был удалён до обработки строки.']]);
                    }
                    app(ProductManagementService::class)->update($import->user, $product, $operation['updates']);
                    $locked->forceFill(['status' => 'completed'])->save();
                    $import->increment('updated_rows');
                } catch (ValidationException $exception) {
                    $locked->forceFill(['status' => 'failed'])->save();
                    $import->rowErrors()->create([
                        'row_number' => $locked->row_number, 'name' => $locked->name,
                        'messages' => array_map(fn (mixed $message) => "Лист «{$operation['sheet']}», строка {$operation['source_row']}: {$message}", collect($exception->errors())->flatten()->all()),
                        'values' => ['sheet' => $operation['sheet'], 'row' => $operation['source_row'], 'sku' => $locked->name, 'updates' => $operation['updates']],
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
        $path = tempnam(storage_path('app'), 'price-status-errors-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать отчёт об ошибках.');
        }
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Ошибки');
            $writer->addRow($this->row(['Лист', 'Строка', 'SKU', 'Изменения', 'Ошибки'], $this->headerStyle()));
            foreach ($import->rowErrors()->get() as $error) {
                $values = $error->values ?? [];
                $writer->addRow($this->row([$values['sheet'] ?? '—', $values['row'] ?? '—', $values['sku'] ?? $error->name, json_encode($values['updates'] ?? [], JSON_UNESCAPED_UNICODE), implode('; ', $error->messages ?? [])]));
            }
            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => "product-price-status-import-{$import->id}-errors.xlsx"];
    }

    /** @return list<array{sheet: string, row: int, sku: string, updates: array<string, mixed>, values: list<mixed>}> */
    private function read(string $path): array
    {
        $options = new ReaderOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        $entries = [];
        $seenSheets = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                $name = $sheet->getName();
                if (! array_key_exists($name, self::HEADERS)) {
                    continue;
                }
                $seenSheets[$name] = true;
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $values = array_map(fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value, $row->toArray());
                    if ($rowNumber === 1) {
                        if ($values !== self::HEADERS[$name]) {
                            throw ValidationException::withMessages(['file' => ["Лист «{$name}»: не изменяйте заголовки шаблона."]]);
                        }

                        continue;
                    }
                    if (collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                        continue;
                    }
                    if ($rowNumber > self::MAX_ROWS_PER_SHEET + 1) {
                        throw ValidationException::withMessages(['file' => ["Лист «{$name}» поддерживает максимум ".self::MAX_ROWS_PER_SHEET.' строк.']]);
                    }
                    $sku = trim((string) ($values[0] ?? ''));
                    $updates = match ($name) {
                        'Цены' => ['price' => $values[1] ?? null, 'old_price' => $this->nullable($values[2] ?? null)],
                        'Активность' => ['is_active' => $values[1] ?? null],
                        default => ['is_on_sale' => $values[1] ?? null],
                    };
                    $entries[] = ['sheet' => $name, 'row' => $rowNumber, 'sku' => $sku, 'updates' => $updates, 'values' => $values];
                }
            }
            if (count($seenSheets) !== 3) {
                throw ValidationException::withMessages(['file' => ['Файл должен содержать листы «Цены», «Активность» и «Распродажа» из шаблона.']]);
            }
            if ($entries === []) {
                throw ValidationException::withMessages(['file' => ['Заполните хотя бы одну строку на любом листе.']]);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => ['Не удалось прочитать XLSX-файл. Проверьте, что он не повреждён.']]);
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
            }
        }

        return $entries;
    }

    /** @param array<string, array<string, true>> $seen @param array{sheet: string, row: int, sku: string, updates: array<string,mixed>} $entry @return list<string> */
    private function validate(array &$seen, array &$entry): array
    {
        $messages = [];
        if ($entry['sku'] === '') {
            $messages[] = 'Укажите SKU.';
        }
        if (isset($seen[$entry['sheet']][$entry['sku']])) {
            $messages[] = 'SKU повторяется на этом листе.';
        }
        $seen[$entry['sheet']][$entry['sku']] = true;
        if ($entry['sheet'] === 'Цены') {
            foreach (['price' => 'Цена', 'old_price' => 'Старая цена'] as $field => $label) {
                $value = $entry['updates'][$field];
                if ($field === 'old_price' && $value === null) {
                    continue;
                }
                if (! is_numeric($value) || (float) $value < 0 || (float) $value > 9999999999.99) {
                    $messages[] = "{$label}: укажите сумму от 0 до 9 999 999 999,99.";
                }
            }
            if (is_numeric($entry['updates']['price']) && $entry['updates']['old_price'] !== null && is_numeric($entry['updates']['old_price']) && (float) $entry['updates']['old_price'] < (float) $entry['updates']['price']) {
                $messages[] = 'Старая цена не может быть меньше актуальной.';
            }
        } else {
            $field = $entry['sheet'] === 'Активность' ? 'is_active' : 'is_on_sale';
            $boolean = $this->boolean($entry['updates'][$field]);
            if ($boolean === null) {
                $messages[] = 'Выберите «Да» или «Нет».';
            } else {
                $entry['updates'][$field] = $boolean;
            }
        }

        return $messages;
    }

    private function nullable(mixed $value): mixed
    {
        return $value === null || trim((string) $value) === '' ? null : $value;
    }

    private function boolean(mixed $value): ?bool
    {
        $value = mb_strtolower(trim((string) $value));

        return in_array($value, ['да', '1'], true) ? true : (in_array($value, ['нет', '0'], true) ? false : null);
    }

    private function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn ($value) => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $values), $style);
    }

    private function headerStyle(): Style
    {
        return (new Style)->setFontBold()->setFontColor('FFFFFF')->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER)->setBackgroundColor('23456B')->setShouldWrapText();
    }

    private function addBooleanValidation(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть шаблон Excel.');
        }
        try {
            foreach ([2, 3] as $sheetNumber) {
                $xml = new DOMDocument;
                $xml->loadXML($zip->getFromName("xl/worksheets/sheet{$sheetNumber}.xml"), LIBXML_NONET);
                $validations = $xml->createElement('dataValidations');
                $validations->setAttribute('count', '1');
                $validation = $xml->createElement('dataValidation');
                foreach (['type' => 'list', 'allowBlank' => '1', 'showErrorMessage' => '1', 'showInputMessage' => '1', 'sqref' => 'B2:B5001'] as $key => $value) {
                    $validation->setAttribute($key, $value);
                }
                $validation->appendChild($xml->createElement('formula1', '"Да,Нет"'));
                $validations->appendChild($validation);
                // SpreadsheetML requires dataValidations before trailing elements such as
                // pageMargins and legacyDrawing. Appending it after OpenSpout's legacyDrawing
                // makes Excel repair the workbook on open.
                $before = null;
                foreach ($xml->documentElement->childNodes as $child) {
                    if (in_array($child->localName, ['hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks', 'colBreaks', 'customProperties', 'cellWatches', 'ignoredErrors', 'smartTags', 'drawing', 'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'webPublishItems', 'tableParts', 'extLst'], true)) {
                        $before = $child;
                        break;
                    }
                }
                $xml->documentElement->insertBefore($validations, $before);
                $zip->addFromString("xl/worksheets/sheet{$sheetNumber}.xml", $xml->saveXML());
            }
        } finally {
            $zip->close();
        }
    }
}
