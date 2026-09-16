<?php

namespace App\Services;

use App\Models\Attribute;
use App\Support\ProductWorkbookSchema;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;
use Throwable;

/**
 * Reads and normalizes the two supported product workbook layouts without planning mutations.
 *
 * @phpstan-type ImportRow array{row: int, values: list<mixed>}
 * @phpstan-type Workbook array{headers: list<string>, rows: list<ImportRow>, seoProducts: array<string, array<string, mixed>>, localized: bool}
 */
final class ProductWorkbookReader
{
    public function __construct(private readonly ProductImportValueParser $values) {}

    /** @return Workbook */
    public function read(string $path): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);
            $headers = [];
            $rows = [];
            $seoProducts = [];
            $seoAttributes = [];
            $firstSheet = true;

            foreach ($reader->getSheetIterator() as $sheet) {
                if (! $firstSheet) {
                    if (in_array($sheet->getName(), ['SEO товаров', 'SEO характеристик'], true)) {
                        $seoRows = $this->readSeoSheet($sheet);
                        if ($sheet->getName() === 'SEO товаров') {
                            foreach ($seoRows as $seoRow) {
                                $sku = $this->values->nullableString($seoRow['SKU'] ?? null);
                                if ($sku === null || isset($seoProducts[$sku])) {
                                    throw ValidationException::withMessages(['file' => ['На листе SEO товаров SKU должны быть заполнены и не повторяться.']]);
                                }
                                $seoProducts[$sku] = $seoRow;
                            }
                        } else {
                            $seoAttributes = $seoRows;
                        }
                    }

                    continue;
                }
                $firstSheet = false;
                foreach ($sheet->getRowIterator() as $index => $row) {
                    if (! is_int($index)) {
                        continue;
                    }
                    $rowValues = $row->toArray();
                    if ($index === 1) {
                        $headers = $this->headers($rowValues, false);

                        continue;
                    }
                    if ($this->emptyRow($rowValues)) {
                        continue;
                    }
                    if (count($rows) >= ProductWorkbookSchema::MAX_ROWS) {
                        throw ValidationException::withMessages(['file' => ['Один импорт поддерживает не более '.ProductWorkbookSchema::MAX_ROWS.' строк.']]);
                    }
                    if (count($rowValues) > count($headers) && ! $this->emptyRow(array_slice($rowValues, count($headers)))) {
                        $this->values->rowError($index, 'найдены значения за пределами заголовков.');
                    }
                    $rows[] = ['row' => $index, 'values' => array_pad(array_slice($rowValues, 0, count($headers)), count($headers), null)];
                }
            }

            if ($headers === []) {
                throw ValidationException::withMessages(['file' => ['XLSX-файл не содержит строки заголовков.']]);
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['file' => ['XLSX-файл не содержит товаров для импорта.']]);
            }

            $localized = in_array('SKU', $headers, true);

            return [
                'headers' => $localized ? $this->localizedHeaders($headers, $seoAttributes) : $this->headers($headers),
                'rows' => $rows,
                'seoProducts' => $seoProducts,
                'localized' => $localized,
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => ['Не удалось прочитать XLSX-файл. Проверьте, что файл не повреждён.']]);
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
                // Preserve the primary validation error when reader cleanup also fails.
            }
        }
    }

    /** @param list<mixed> $values
     * @return list<string>
     */
    private function headers(array $values, bool $requireLegacy = true): array
    {
        $headers = array_map(static function (mixed $value): string {
            if (! is_string($value)) {
                return '';
            }

            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
        }, $values);

        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }
        if ($headers === [] || in_array('', $headers, true)) {
            throw ValidationException::withMessages(['file' => ['Все заголовки XLSX должны быть непустыми текстовыми значениями.']]);
        }
        if (count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['file' => ['Заголовки XLSX не должны повторяться.']]);
        }
        $missing = $requireLegacy ? array_values(array_diff(ProductWorkbookSchema::REQUIRED_IMPORT_HEADERS, $headers)) : [];
        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => ['В XLSX отсутствуют обязательные столбцы: '.implode(', ', $missing).'.']]);
        }

        return $headers;
    }

    /** @return list<array<string, mixed>> */
    private function readSeoSheet(Sheet $sheet): array
    {
        $headers = [];
        $rows = [];
        foreach ($sheet->getRowIterator() as $index => $row) {
            $rowValues = $row->toArray();
            if ($index === 1) {
                $headers = $this->headers($rowValues, false);

                continue;
            }
            if ($this->emptyRow($rowValues)) {
                continue;
            }
            if (count($rows) >= ProductWorkbookSchema::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => ['На листе '.$sheet->getName().' превышен лимит строк.']]);
            }
            $rows[] = array_combine($headers, array_pad(array_slice($rowValues, 0, count($headers)), count($headers), null));
        }

        return $rows;
    }

    /** @param list<string> $headers
     * @param  list<array<string, mixed>>  $seoAttributes
     * @return list<string>
     */
    private function localizedHeaders(array $headers, array $seoAttributes): array
    {
        $baseHeaders = array_flip(ProductWorkbookSchema::MANAGER_HEADERS);
        $baseHeaders['Основное изображение'] = 'primary_image_url';
        $attributeHeaders = [];
        if ($seoAttributes !== []) {
            foreach ($seoAttributes as $attribute) {
                $name = $this->values->requiredString($attribute['Название'] ?? null, 1, 'Название характеристики');
                $unit = $this->values->nullableString($attribute['Единица измерения'] ?? null);
                $header = $name.($unit === null ? '' : ' ('.$unit.')');
                $attributeHeaders[$header][] = $this->values->requiredString($attribute['URL характеристики (slug)'] ?? null, 1, 'URL характеристики (slug)');
            }
        } else {
            foreach (Attribute::query()->get() as $attribute) {
                $header = $attribute->name.($attribute->unit ? ' ('.$attribute->unit.')' : '');
                $attributeHeaders[$header][] = $attribute->slug;
            }
        }

        $normalized = [];
        foreach ($headers as $header) {
            if (isset($baseHeaders[$header])) {
                $normalized[] = $baseHeaders[$header];

                continue;
            }
            $matches = $attributeHeaders[$header] ?? [];
            if ($matches === [] && preg_match('/^Изображение ([1-9][0-9]*)$/u', $header, $imageColumn)) {
                $normalized[] = 'image_url.'.$imageColumn[1];

                continue;
            }
            if (count($matches) !== 1) {
                throw ValidationException::withMessages(['file' => ["Столбец «{$header}» не найден или соответствует нескольким характеристикам. Проверьте лист SEO характеристик."]]);
            }
            $normalized[] = 'attribute.'.$matches[0];
        }
        if (count($normalized) !== count(array_unique($normalized))) {
            throw ValidationException::withMessages(['file' => ['Несколько столбцов ссылаются на одну характеристику.']]);
        }
        $required = ['sku', 'name', 'category_name', 'brand_name', 'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale'];
        $missing = array_diff($required, $normalized);
        if ($missing !== []) {
            $labels = array_map(static fn (string $field): string => ProductWorkbookSchema::MANAGER_HEADERS[$field], $missing);
            throw ValidationException::withMessages(['file' => ['В XLSX отсутствуют обязательные столбцы: '.implode(', ', $labels).'.']]);
        }

        return $normalized;
    }

    /** @param list<mixed> $values */
    private function emptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                return false;
            }
        }

        return true;
    }
}
