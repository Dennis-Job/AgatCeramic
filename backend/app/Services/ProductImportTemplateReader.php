<?php

namespace App\Services;

use App\Models\Category;
use App\Support\ProductWorkbookSchema;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

final class ProductImportTemplateReader
{
    public function __construct(private readonly ProductImportTemplateSchema $schema) {}

    /** @return list<array{row: int, editing: bool, values: array<string, mixed>}> */
    public function read(Category $category, string $path): array
    {
        $headers = $this->schema->headers($category);
        $editingHeaders = $this->schema->headers($category, true);
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
                    if (! is_int($index) || count($entries) >= ProductWorkbookSchema::MAX_ROWS || $index > ProductWorkbookSchema::MAX_ROWS + 1) {
                        throw ValidationException::withMessages(['file' => ['Шаблон поддерживает максимум '.ProductWorkbookSchema::MAX_ROWS.' товаров в строках 2–5001.']]);
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
            unset($sheet, $reader);
            gc_collect_cycles();
        }

        return $entries;
    }

    private function integerValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }
}
