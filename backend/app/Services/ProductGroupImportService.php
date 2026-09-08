<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductGroup;
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

/** Dedicated workbook importer; it only changes variation-group membership and axes. */
class ProductGroupImportService
{
    public const MAX_ROWS = 5000;

    /** @var list<string> */
    private const GROUP_HEADERS = ['Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название', 'Ось 1', 'Ось 2', 'Ось 3', 'Ось 4', 'Ось 5', 'Ось 6', 'Ось 7', 'Ось 8', 'Ось 9', 'Ось 10', 'Ось 11', 'Ось 12', 'Ось 13', 'Ось 14', 'Ось 15', 'Ось 16', 'Ось 17', 'Ось 18', 'Ось 19', 'Ось 20'];

    /** @var list<string> */
    private const MEMBERS_HEADERS = ['Ключ группы', 'SKU'];

    /** @var array<string, string> */
    private const ACTIONS = ['не изменять' => 'none', 'создать' => 'create', 'изменить' => 'update', 'расформировать' => 'disband'];

    /** @return array{path: string, name: string} */
    public function createTemplate(): array
    {
        $path = tempnam(storage_path('app'), 'product-groups-template-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать файл шаблона.');
        }
        $writer = new Writer;
        try {
            $availableAxisValues = Attribute::query()->whereNotIn('type', ['text', 'multiselect'])->orderBy('name')->orderBy('id')->get();
            $axisChoices = $this->axisChoices($availableAxisValues);
            $writer->openToFile($path);
            $groupsSheet = $writer->getCurrentSheet();
            $groupsSheet->setName('Группы')->setSheetView((new SheetView)->setFreezeRow(2));
            $writer->addRow($this->row(self::GROUP_HEADERS, $this->headerStyle())->setHeight(36));
            foreach (range(1, count(self::GROUP_HEADERS)) as $column) {
                $groupsSheet->setColumnWidth($column <= 5 ? 24 : 18, $column);
            }
            ProductGroup::query()->with(['axes', 'products'])->orderBy('code')->get()->each(function (ProductGroup $group) use ($writer, $axisChoices): void {
                $axes = $group->axes->map(fn (Attribute $attribute): string => $axisChoices[$attribute->id] ?? $attribute->name)->all();
                $writer->addRow($this->row(['Не изменять', $group->code, $group->code, $group->code, $group->name, ...$axes]));
            });
            $members = $writer->addNewSheetAndMakeItCurrent();
            $members->setName('Состав')->setSheetView((new SheetView)->setFreezeRow(2));
            $members->setColumnWidth(24, 1, 2);
            $writer->addRow($this->row(self::MEMBERS_HEADERS, $this->headerStyle())->setHeight(36));
            ProductGroup::query()->with('products')->orderBy('code')->get()->each(function (ProductGroup $group) use ($writer): void {
                foreach ($group->products as $product) {
                    $writer->addRow($this->row([$group->code, $product->sku]));
                }
            });
            $instructions = $writer->addNewSheetAndMakeItCurrent();
            $instructions->setName('Инструкция')->setSheetView((new SheetView)->setFreezeRow(2));
            $instructions->setColumnWidth(26, 1);
            $instructions->setColumnWidth(115, 2);
            $writer->addRow($this->row(['Раздел', 'Что нужно сделать'], $this->headerStyle())->setHeight(36));
            foreach ($this->instructions() as [$section, $text]) {
                $writer->addRow($this->row([$section, $text], $this->instructionStyle())->setHeight(54));
            }
            $writer->close();
            $this->addValidations($path, array_values($axisChoices));
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => 'product-groups-import-template.xlsx'];
    }

    public function initialize(ProductImport $import, string $path): void
    {
        $workbook = $this->read($path);
        $errors = $workbook['errors'];
        $changes = $this->buildChanges($workbook['groups'], $workbook['members'], $errors);
        if ($errors === [] && $changes !== []) {
            try {
                DB::beginTransaction();
                app(ProductGroupManagementService::class)->applyBulk($import->user, $changes);
                DB::rollBack();
            } catch (ValidationException $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $errors[] = ['sheet' => 'Группы', 'row' => 1, 'name' => null, 'messages' => collect($exception->errors())->flatten()->values()->all(), 'values' => []];
            } catch (Throwable $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                throw $exception;
            }
        }
        foreach ($errors as $index => $error) {
            $import->rowErrors()->create([
                // Product import errors have a per-import unique row number; keep
                // the workbook sheet/real row in values for a useful report.
                'row_number' => 100000 + $index,
                'name' => $error['name'],
                'messages' => array_map(fn ($message) => "Лист «{$error['sheet']}», строка {$error['row']}: {$message}", $error['messages']),
                'values' => $error,
            ]);
        }
        $import->forceFill(['total_rows' => count($workbook['groups']), 'failed_rows' => count($errors), 'processed_rows' => count($errors)])->save();
        if ($errors === [] && $changes !== []) {
            ProductImportItem::query()->create([
                'product_import_id' => $import->id, 'row_number' => 1, 'name' => 'Группы вариантов',
                'payload' => ['changes' => $changes], 'attribute_payload' => [], 'status' => 'pending',
            ]);
        }
    }

    public function process(ProductImport $import): bool
    {
        $item = $import->items()->where('status', 'pending')->first();
        if ($item === null) {
            return true;
        }
        DB::transaction(function () use ($import, $item): void {
            $item = ProductImportItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->status !== 'pending') {
                return;
            }
            try {
                $changes = $item->payload['changes'];
                app(ProductGroupManagementService::class)->applyBulk($import->user, $changes);
                $item->forceFill(['status' => 'completed'])->save();
                $import->increment('created_rows', collect($changes)->where('action', 'create')->count());
                $import->increment('updated_rows', collect($changes)->whereIn('action', ['update', 'disband'])->count());
            } catch (ValidationException $exception) {
                $item->forceFill(['status' => 'failed'])->save();
                $import->rowErrors()->create(['row_number' => 1, 'name' => $item->name, 'messages' => collect($exception->errors())->flatten()->values()->all(), 'values' => ['sheet' => 'Группы', 'row' => '—', 'name' => $item->name, 'messages' => collect($exception->errors())->flatten()->values()->all()]]);
                $import->increment('failed_rows');
            }
            $import->increment('processed_rows');
        });

        return true;
    }

    /** @return array{path: string, name: string} */
    public function createErrorReport(ProductImport $import): array
    {
        $path = tempnam(storage_path('app'), 'product-groups-errors-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать отчёт об ошибках.');
        }
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Ошибки');
            $writer->addRow($this->row(['Лист', 'Строка', 'Группа / SKU', 'Ошибки'], $this->headerStyle()));
            foreach ($import->rowErrors()->get() as $error) {
                $v = $error->values ?? [];
                $writer->addRow($this->row([$v['sheet'] ?? '—', $v['row'] ?? '—', $v['name'] ?? $error->name ?? '—', implode('; ', $error->messages ?? [])]));
            }
            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            } @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => "product-group-import-{$import->id}-errors.xlsx"];
    }

    /** @return array{groups: list<array<string,mixed>>, members: array<string,list<array{sku:string,row:int}>>, errors:list<array<string,mixed>>} */
    private function read(string $path): array
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
                    $values = array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v), $row->toArray());
                    $headers = $sheetName === 'Группы' ? self::GROUP_HEADERS : self::MEMBERS_HEADERS;
                    if ($number === 1) {
                        if ($values !== $headers) {
                            throw ValidationException::withMessages(['file' => ["Лист «{$sheetName}»: не изменяйте заголовки шаблона."]]);
                        }

                        continue;
                    }
                    if ($values === [] || collect($values)->every(fn ($v) => $v === '')) {
                        continue;
                    }
                    if ($number > self::MAX_ROWS + 1) {
                        throw ValidationException::withMessages(['file' => ["Лист «{$sheetName}» поддерживает максимум ".self::MAX_ROWS.' строк.']]);
                    }
                    if ($sheetName === 'Группы') {
                        $groups[] = ['row' => $number, 'action' => $values[0] ?? '', 'key' => $values[1] ?? '', 'source' => $values[2] ?? '', 'code' => $values[3] ?? '', 'name' => $values[4] ?? '', 'axes' => array_values(array_filter(array_slice($values, 5, 20), fn ($v) => $v !== ''))];
                    } else {
                        $key = $values[0] ?? '';
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
        } catch (ValidationException $e) {
            throw $e;
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

    /** @param list<array<string,mixed>> $groups @param array<string,list<array{sku:string,row:int}>> $members @param list<array<string,mixed>> $errors @return list<array<string,mixed>> */
    private function buildChanges(array $groups, array $members, array &$errors): array
    {
        $changes = [];
        $keys = [];
        $targetCodes = [];
        $axisChoices = $this->axisChoices(Attribute::query()->whereNotIn('type', ['text', 'multiselect'])->orderBy('name')->orderBy('id')->get());
        $axisIdsByLabel = array_flip($axisChoices);
        foreach ($groups as $row) {
            $action = self::ACTIONS[mb_strtolower($row['action'])] ?? null;
            if ($action === null) {
                $errors[] = $this->groupError($row, 'Выберите действие: «Не изменять», «Создать», «Изменить» или «Расформировать».');

                continue;
            }
            if ($action === 'none') {
                continue;
            }
            if ($row['key'] === '') {
                $errors[] = $this->groupError($row, 'Укажите ключ группы.');

                continue;
            }
            if (isset($keys[$row['key']])) {
                $errors[] = $this->groupError($row, 'Ключ группы повторяется на листе «Группы».');

                continue;
            }
            $keys[$row['key']] = true;
            $source = $row['source'] !== '' ? $row['source'] : $row['key'];
            $group = $action === 'create' ? null : ProductGroup::query()->where('code', $source)->first();
            if ($action !== 'create' && $group === null) {
                $errors[] = $this->groupError($row, "Группа с исходным кодом «{$source}» не найдена.");

                continue;
            }
            if ($action === 'disband') {
                $changes[] = ['action' => 'disband', 'group_id' => $group->id];

                continue;
            }
            if ($row['code'] === '' || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $row['code'])) {
                $errors[] = $this->groupError($row, 'Укажите код группы латиницей, цифрами, точкой, дефисом или подчёркиванием.');

                continue;
            }
            if ($row['name'] === '' || mb_strlen($row['name']) > 255) {
                $errors[] = $this->groupError($row, 'Укажите название группы длиной до 255 символов.');

                continue;
            }
            if (isset($targetCodes[$row['code']])) {
                $errors[] = $this->groupError($row, 'Итоговый код группы повторяется в файле.');

                continue;
            }
            $targetCodes[$row['code']] = true;
            $axisIds = [];
            foreach ($row['axes'] as $axis) {
                if (isset($axisIdsByLabel[$axis])) {
                    $axisIds[] = (int) $axisIdsByLabel[$axis];

                    continue;
                }
                if (! preg_match('/^(\d+)\s*:/', $axis, $match) || ! isset($axisChoices[(int) $match[1]])) {
                    $errors[] = $this->groupError($row, 'Выберите ось из выпадающего списка шаблона.');

                    continue 2;
                }
                $axisIds[] = (int) $match[1];
            }
            if ($axisIds === [] || count($axisIds) !== count(array_unique($axisIds))) {
                $errors[] = $this->groupError($row, 'Укажите от одной до двадцати неповторяющихся осей.');

                continue;
            }
            $memberRows = $members[$row['key']] ?? [];
            if (count($memberRows) < 2 || count($memberRows) > 500) {
                $errors[] = $this->groupError($row, 'Для создания или изменения укажите полный состав из 2–500 SKU на листе «Состав».');

                continue;
            }
            $skus = array_column($memberRows, 'sku');
            if (count($skus) !== count(array_unique($skus))) {
                $errors[] = $this->groupError($row, 'SKU не должен повторяться в составе одной группы.');

                continue;
            }
            $products = Product::query()->whereIn('sku', $skus)->get()->keyBy('sku');
            if ($products->count() !== count($skus)) {
                $errors[] = $this->groupError($row, 'Один или несколько SKU из состава не найдены.');

                continue;
            }
            $changes[] = ['action' => $action, 'group_id' => $group?->id, 'code' => $row['code'], 'name' => $row['name'], 'axis_attribute_ids' => $axisIds, 'product_ids' => collect($skus)->map(fn ($sku) => $products[$sku]->id)->all()];
        }
        // A SKU may appear in only one intended final composition.
        $seenProducts = [];
        foreach ($changes as $change) {
            foreach ($change['product_ids'] ?? [] as $id) {
                if (isset($seenProducts[$id])) {
                    $errors[] = ['sheet' => 'Состав', 'row' => '—', 'name' => (string) $id, 'messages' => ['SKU включён в состав нескольких итоговых групп.'], 'values' => []];
                }
                $seenProducts[$id] = true;
            }
        }

        return $changes;
    }

    /** @return array<string,mixed> */
    private function groupError(array $row, string $message): array
    {
        return ['sheet' => 'Группы', 'row' => $row['row'], 'name' => $row['key'] ?: null, 'messages' => [$message], 'values' => $row];
    }

    private function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn ($v) => is_string($v) ? new StringCell($v, null) : Cell::fromValue($v), $values), $style);
    }

    private function headerStyle(): Style
    {
        return (new Style)->setFontBold()->setFontColor('FFFFFF')->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER)->setBackgroundColor('23456B')->setShouldWrapText();
    }

    /** @return list<array{string, string}> */
    private function instructions(): array
    {
        return [
            ['Перед началом', 'Скачайте свежую выгрузку перед каждой загрузкой. Не меняйте названия листов и заголовки столбцов. Заполнять нужно только листы «Группы» и «Состав»; этот лист служит справкой.'],
            ['Общий принцип', 'Один файл применяется целиком: если система найдёт ошибку, изменения из файла не будут внесены. После исправления скачайте отчёт с ошибками, поправьте исходный файл и загрузите его повторно.'],
            ['Лист «Группы»', 'Каждая строка описывает одно действие с группой вариантов. Строки с действием «Не изменять» остаются в выгрузке для справки и не меняют данные.'],
            ['Действие: Создать', 'Укажите уникальный «Ключ группы», новый «Код группы», «Название», от одной до двадцати осей и полный состав SKU на листе «Состав». «Исходный код» для новой группы не заполняйте.'],
            ['Действие: Изменить', 'Для существующей группы сохраните «Ключ группы» из выгрузки. В «Исходный код» укажите текущий код группы. Заполните итоговые код, название и оси. Чтобы переименовать группу, укажите старый код в «Исходном коде», а новый — в «Коде группы».'],
            ['Действие: Расформировать', 'Укажите «Ключ группы» и текущий «Исходный код». Остальные поля и строки этой группы на листе «Состав» не заполняйте: группа будет удалена, а товары останутся самостоятельными.'],
            ['Ключ группы', 'Это техническая связь между листами. Для выгруженной группы не изменяйте ключ. Для новой группы придумайте уникальный ключ и используйте его точно так же в столбце «Ключ группы» на листе «Состав».'],
            ['Код и название', 'Код группы должен содержать только латинские буквы, цифры, точку, дефис или подчёркивание. Название обязательно и может содержать до 255 символов. Итоговые коды не должны повторяться.'],
            ['Оси вариантов', 'В «Ось 1»–«Ось 20» выбирайте названия из выпадающего списка. ID характеристик и технический справочник скрыты и определяются системой автоматически. Нужна хотя бы одна ось; одна и та же ось не должна повторяться.'],
            ['Лист «Состав»', 'Для каждой создаваемой или изменяемой группы перечислите полный итоговый набор SKU: один SKU в строке. В группе должно быть от 2 до 500 SKU. Один SKU может входить только в одну итоговую группу.'],
            ['Перенос товара', 'Чтобы перенести товар между группами, укажите его SKU в итоговом составе новой группы и не указывайте в итоговом составе прежней. Оба изменения можно выполнить в одном файле.'],
            ['Проверка перед загрузкой', 'Проверьте, что все SKU существуют, действие выбрано из списка, а для «Создать» и «Изменить» заполнены код, название, оси и состав. Сохраните файл в формате XLSX и загрузите его через раздел «Товары» → «Группы вариантов».'],
        ];
    }

    private function instructionStyle(): Style
    {
        return (new Style)->setCellVerticalAlignment(CellVerticalAlignment::TOP)->setShouldWrapText();
    }

    /** @param iterable<Attribute> $attributes @return array<int, string> */
    private function axisChoices(iterable $attributes): array
    {
        $items = is_array($attributes) ? $attributes : iterator_to_array($attributes);
        $counts = [];
        foreach ($items as $attribute) {
            $key = mb_strtolower(trim($attribute->name));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        $choices = [];
        foreach ($items as $attribute) {
            $key = mb_strtolower(trim($attribute->name));
            $choices[$attribute->id] = $counts[$key] > 1 ? "{$attribute->name} (ID {$attribute->id})" : $attribute->name;
        }

        return $choices;
    }

    /** @param list<string> $availableAxes */
    private function addValidations(string $path, array $availableAxes): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть шаблон Excel.');
        }
        try {
            $xml = new DOMDocument;
            $content = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($content === false || ! $xml->loadXML($content, LIBXML_NONET)) {
                throw new RuntimeException('Не удалось подготовить шаблон Excel.');
            }
            $validations = $xml->createElement('dataValidations');
            $validations->setAttribute('count', '2');
            $validation = $xml->createElement('dataValidation');
            foreach (['type' => 'list', 'allowBlank' => '1', 'showErrorMessage' => '1', 'showInputMessage' => '1', 'sqref' => 'A2:A5001'] as $key => $value) {
                $validation->setAttribute($key, $value);
            }
            $validation->appendChild($xml->createElement('formula1', '"Не изменять,Создать,Изменить,Расформировать"'));
            $validations->appendChild($validation);
            $axisValidation = $xml->createElement('dataValidation');
            foreach (['type' => 'list', 'allowBlank' => '1', 'showErrorMessage' => '1', 'showInputMessage' => '1', 'sqref' => 'F2:Y5001'] as $key => $value) {
                $axisValidation->setAttribute($key, $value);
            }
            $axisValidation->appendChild($xml->createElement('formula1', '$Z$2:$Z$'.max(2, count($availableAxes) + 1)));
            $validations->appendChild($axisValidation);
            $before = null;
            foreach ($xml->documentElement->childNodes as $child) {
                if (in_array($child->localName, ['hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks', 'colBreaks', 'drawing', 'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'tableParts', 'extLst'], true)) {
                    $before = $child;
                    break;
                }
            }
            $xml->documentElement->insertBefore($validations, $before);
            $this->addAxisHelperCells($xml, $availableAxes);
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml->saveXML());
            $this->normalizeStylesForExcel($zip);
        } finally {
            $zip->close();
        }
    }

    /** @param list<string> $availableAxes */
    private function addAxisHelperCells(DOMDocument $xml, array $availableAxes): void
    {
        $sheetData = $xml->getElementsByTagName('sheetData')->item(0);
        if ($sheetData === null) {
            throw new RuntimeException('Не удалось подготовить список осей в шаблоне Excel.');
        }
        $rows = [];
        foreach ($sheetData->childNodes as $row) {
            if ($row->localName === 'row') {
                $rows[(int) $row->getAttribute('r')] = $row;
            }
        }
        foreach ($availableAxes as $index => $axis) {
            $rowNumber = $index + 2;
            $row = $rows[$rowNumber] ?? $xml->createElement('row');
            if (! isset($rows[$rowNumber])) {
                $row->setAttribute('r', (string) $rowNumber);
                $sheetData->appendChild($row);
                $rows[$rowNumber] = $row;
            }
            $cell = $xml->createElement('c');
            $cell->setAttribute('r', 'Z'.$rowNumber);
            $cell->setAttribute('t', 'inlineStr');
            $inlineString = $xml->createElement('is');
            $text = $xml->createElement('t');
            $text->appendChild($xml->createTextNode($axis));
            $inlineString->appendChild($text);
            $cell->appendChild($inlineString);
            $row->appendChild($cell);
        }
        $cols = $xml->getElementsByTagName('cols')->item(0);
        if ($cols !== null) {
            $column = $xml->createElement('col');
            foreach (['min' => '26', 'max' => '26', 'width' => '2', 'hidden' => '1', 'customWidth' => '1'] as $attribute => $value) {
                $column->setAttribute($attribute, $value);
            }
            $cols->appendChild($column);
        }
        $dimension = $xml->getElementsByTagName('dimension')->item(0);
        if ($dimension !== null) {
            $dimension->setAttribute('ref', 'A1:Z'.max(array_keys($rows) ?: [1]));
        }
    }

    /** OpenSpout uses six-digit RGB values and font child ordering that Excel repairs on open. */
    private function normalizeStylesForExcel(ZipArchive $zip): void
    {
        $xml = new DOMDocument;
        $content = $zip->getFromName('xl/styles.xml');
        if ($content === false || ! $xml->loadXML($content, LIBXML_NONET)) {
            throw new RuntimeException('Не удалось подготовить стили шаблона Excel.');
        }
        foreach ($xml->getElementsByTagName('fgColor') as $color) {
            if (strlen($color->getAttribute('rgb')) === 6) {
                $color->setAttribute('rgb', 'FF'.$color->getAttribute('rgb'));
            }
        }
        foreach ($xml->getElementsByTagName('font') as $font) {
            $children = iterator_to_array($font->childNodes);
            foreach (['b', 'i', 'strike', 'condense', 'extend', 'outline', 'shadow', 'u', 'vertAlign', 'sz', 'color', 'name', 'family', 'charset', 'scheme'] as $name) {
                foreach ($children as $child) {
                    if ($child->localName === $name) {
                        $font->appendChild($child);
                    }
                }
            }
        }
        $zip->addFromString('xl/styles.xml', $xml->saveXML());
    }
}
