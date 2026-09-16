<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

final class ProductPriceStatusWorkbookReader
{
    public const MAX_ROWS_PER_SHEET = 5000;

    /** @var array<string, list<string>> */
    public const HEADERS = [
        'Цены' => ['SKU *', 'Цена *', 'Старая цена'],
        'Активность' => ['SKU *', 'Активность *'],
        'Распродажа' => ['SKU *', 'Распродажа *'],
    ];

    /** @return list<array{sheet: string, row: int, sku: string, updates: array<string, mixed>, values: list<mixed>}> */
    public function read(string $path): array
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
                    if (collect($values)->every(fn (mixed $value): bool => $value === null || trim($this->stringValue($value)) === '')) {
                        continue;
                    }
                    if (! is_int($rowNumber) || $rowNumber > self::MAX_ROWS_PER_SHEET + 1) {
                        throw ValidationException::withMessages(['file' => ["Лист «{$name}» поддерживает максимум ".self::MAX_ROWS_PER_SHEET.' строк.']]);
                    }
                    $sku = trim($this->stringValue($values[0] ?? ''));
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

    /**
     * @param  array<string, array<string, true>>  $seen
     * @param  array{sheet: string, row: int, sku: string, updates: array<string,mixed>, values: list<mixed>}  $entry
     * @return list<string>
     */
    public function validate(array &$seen, array &$entry): array
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
        return $value === null || trim($this->stringValue($value)) === '' ? null : $value;
    }

    private function boolean(mixed $value): ?bool
    {
        $value = mb_strtolower(trim($this->stringValue($value)));

        return in_array($value, ['да', '1'], true) ? true : (in_array($value, ['нет', '0'], true) ? false : null);
    }

    public function stringValue(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_object($value) && method_exists($value, '__toString') ? (string) $value : '';
    }
}
