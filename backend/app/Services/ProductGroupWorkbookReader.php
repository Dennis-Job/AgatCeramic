<?php

namespace App\Services;

use App\Support\ProductGroupWorkbookSchema;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * @phpstan-type GroupRow array{row: int, action: string, key: string, source: string, code: string, name: string, axes: list<string>, individuals?: list<string>}
 * @phpstan-type MemberRow array{sku: string, row: int}
 * @phpstan-type ImportError array{sheet: string, row: int|string, name: string|null, messages: list<string>, values: array<int|string, mixed>}
 */
final class ProductGroupWorkbookReader
{
    private const MEMBERS_HEADERS = ['Ключ группы', 'SKU'];

    /** @return array{groups: list<GroupRow>, members: array<string,list<MemberRow>>, errors:list<ImportError>} */
    public function read(string $path): array
    {
        $reader = new Reader(new ReaderOptions);
        $groups = [];
        $members = [];
        $errors = [];
        $seen = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetName = $sheet->getName();
                if (! in_array($sheetName, ['Группы', 'Состав'], true)) {
                    continue;
                }
                $seen[$sheetName] = true;
                $headers = $sheetName === 'Группы' ? ProductGroupWorkbookSchema::headers() : self::MEMBERS_HEADERS;
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if (! is_int($number)) {
                        continue;
                    }
                    // OpenSpout may return sparse indexes when Excel's row spans omit hidden helpers.
                    $values = array_replace(array_fill(0, $row->getNumCells(), ''), array_map(fn (mixed $value): string => $this->cellValue($value), $row->toArray()));
                    if ($number === 1) {
                        // Excel can extend the header row to the hidden axis helper column.
                        while ($values !== [] && end($values) === '') {
                            array_pop($values);
                        }
                        if ($sheetName === 'Группы' && $values === ProductGroupWorkbookSchema::legacyHeaders()) {
                            $headers = $values;
                        } elseif ($values !== $headers) {
                            throw ValidationException::withMessages(['file' => ["Лист «{$sheetName}»: не изменяйте заголовки шаблона."]]);
                        }

                        continue;
                    }
                    $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
                    if ($values === [] || collect($values)->every(fn ($value) => $value === '')) {
                        continue;
                    }
                    if ($number > ProductGroupImportService::MAX_ROWS + 1) {
                        throw ValidationException::withMessages(['file' => ["Лист «{$sheetName}» поддерживает максимум ".ProductGroupImportService::MAX_ROWS.' строк.']]);
                    }
                    if ($sheetName === 'Группы') {
                        $group = ['row' => $number, 'action' => $values[0], 'key' => $values[1], 'source' => $values[2], 'code' => $values[3], 'name' => $values[4], 'axes' => array_values(array_filter(array_slice($values, 5, 20), fn (string $value): bool => $value !== ''))];
                        if ($headers === ProductGroupWorkbookSchema::headers()) {
                            $group['individuals'] = array_values(array_filter(array_slice($values, 25, 20), fn (string $value): bool => $value !== ''));
                        }
                        $groups[] = $group;
                    } else {
                        $key = $values[0];
                        $sku = $values[1] ?? '';
                        if ($key === '' || $sku === '') {
                            $errors[] = ['sheet' => 'Состав', 'row' => $number, 'name' => $key ?: $sku ?: null, 'messages' => ['Укажите ключ группы и SKU.'], 'values' => $values];
                        } else {
                            $members[$key][] = ['sku' => $sku, 'row' => $number];
                        }
                    }
                }
            }
            if (count($seen) !== 2) {
                throw ValidationException::withMessages(['file' => ['Файл должен содержать листы «Группы» и «Состав» из шаблона.']]);
            }
            if ($groups === []) {
                throw ValidationException::withMessages(['file' => ['Заполните хотя бы одну строку на листе «Группы».']]);
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

        return compact('groups', 'members', 'errors');
    }

    private function cellValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
