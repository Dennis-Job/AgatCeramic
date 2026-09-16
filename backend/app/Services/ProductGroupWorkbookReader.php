<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * @phpstan-type GroupRow array{row: int, action: string, key: string, source: string, code: string, name: string, axes: list<string>}
 * @phpstan-type MemberRow array{sku: string, row: int}
 * @phpstan-type ImportError array{sheet: string, row: int|string, name: string|null, messages: list<string>, values: array<int|string, mixed>}
 */
final class ProductGroupWorkbookReader
{
    private const GROUP_HEADERS = ['Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название', 'Ось 1', 'Ось 2', 'Ось 3', 'Ось 4', 'Ось 5', 'Ось 6', 'Ось 7', 'Ось 8', 'Ось 9', 'Ось 10', 'Ось 11', 'Ось 12', 'Ось 13', 'Ось 14', 'Ось 15', 'Ось 16', 'Ось 17', 'Ось 18', 'Ось 19', 'Ось 20'];

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
                foreach ($sheet->getRowIterator() as $number => $row) {
                    if (! is_int($number)) {
                        continue;
                    }
                    $values = array_map(fn (mixed $value): string => $this->cellValue($value), $row->toArray());
                    $headers = $sheetName === 'Группы' ? self::GROUP_HEADERS : self::MEMBERS_HEADERS;
                    if ($number === 1) {
                        if ($values !== $headers) {
                            throw ValidationException::withMessages(['file' => ["Лист «{$sheetName}»: не изменяйте заголовки шаблона."]]);
                        }

                        continue;
                    }
                    $values = array_slice($values, 0, count($headers));
                    if ($values === [] || collect($values)->every(fn ($value) => $value === '')) {
                        continue;
                    }
                    if ($number > ProductGroupImportService::MAX_ROWS + 1) {
                        throw ValidationException::withMessages(['file' => ["Лист «{$sheetName}» поддерживает максимум ".ProductGroupImportService::MAX_ROWS.' строк.']]);
                    }
                    if ($sheetName === 'Группы') {
                        $groups[] = ['row' => $number, 'action' => $values[0], 'key' => $values[1], 'source' => $values[2], 'code' => $values[3], 'name' => $values[4], 'axes' => array_values(array_filter(array_slice($values, 5, 20), fn (string $value): bool => $value !== ''))];
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
