<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\ProductGroup;
use App\Support\ProductGroupWorkbookSchema;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
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
use ZipArchive;

final class ProductGroupImportTemplateService
{
    /** @var list<string> */
    private const MEMBERS_HEADERS = ['Ключ группы', 'SKU'];

    public function __construct(private readonly ProductGroupAxisChoices $axes) {}

    /** @return array{path: string, name: string} */
    public function createTemplate(): array
    {
        $path = tempnam(storage_path('app'), 'product-groups-template-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать файл шаблона.');
        }
        $writer = new Writer;
        try {
            $availableAttributes = Attribute::query()->where('slug', '!=', 'kollektsiya')->orderBy('name')->orderBy('id')->get();
            $axisChoices = $this->axes->forAttributes($availableAttributes->reject(fn (Attribute $attribute): bool => in_array($attribute->type, ['text', 'multiselect'], true)));
            $individualChoices = $this->axes->forAttributes($availableAttributes);
            $writer->openToFile($path);
            $groupsSheet = $writer->getCurrentSheet();
            $groupsSheet->setName('Группы')->setSheetView((new SheetView)->setFreezeRow(2));
            $writer->addRow($this->row(ProductGroupWorkbookSchema::headers(), $this->headerStyle())->setHeight(36));
            foreach (range(1, count(ProductGroupWorkbookSchema::headers())) as $column) {
                $groupsSheet->setColumnWidth($column <= 5 ? 24 : 18, max(1, $column));
            }
            ProductGroup::query()->with(['axes', 'individualAttributes'])->orderBy('code')->get()->each(function (ProductGroup $group) use ($writer, $axisChoices, $individualChoices): void {
                $axes = $group->axes->map(fn (Attribute $attribute): string => $axisChoices[(int) $attribute->id] ?? (string) $attribute->name)->all();
                $individuals = $group->individualAttributes->map(fn (Attribute $attribute): string => $individualChoices[(int) $attribute->id] ?? (string) $attribute->name)->all();
                $writer->addRow($this->row(['Не изменять', $group->code, $group->code, $group->code, $group->name, ...array_pad($axes, ProductGroupWorkbookSchema::ROLE_SLOTS, null), ...$individuals]));
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
            $this->addValidations($path, array_values($axisChoices), array_values($individualChoices));
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

    /** @param list<bool|DateTimeInterface|float|int|string|null> $values */
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
            ['Индивидуальные характеристики', 'В «Индивидуальная 1»–«Индивидуальная 20» выбирайте свойства, которые могут различаться без переключателя на сайте: м² и штук в упаковке, вес, габариты. Значения заполняются у каждого SKU в товарной таблице. Ось нельзя одновременно назначить индивидуальной.'],
            ['Общие характеристики', 'Все характеристики категории, не выбранные осями или индивидуальными, общие: при объединении значения должны совпадать. Изменение или очистка общего значения в товарном импорте применяется ко всей группе.'],
            ['Одинаковые цвет и размер', 'Несколько SKU могут иметь одинаковые значения осей, например при разной фасовке. Каждый SKU остаётся самостоятельным товаром со своими ценой, остатком и индивидуальными параметрами упаковки.'],
            ['Старые файлы', 'Файл без столбцов «Индивидуальная 1»–«Индивидуальная 20» сохраняет индивидуальные роли существующей группы. В новом шаблоне пустые индивидуальные столбцы очищают эти роли; новые общие значения при этом должны совпадать.'],
            ['Лист «Состав»', 'Для каждой создаваемой или изменяемой группы перечислите полный итоговый набор SKU: один SKU в строке. В группе должно быть от 2 до 500 SKU. Один SKU может входить только в одну итоговую группу.'],
            ['Перенос товара', 'Чтобы перенести товар между группами, укажите его SKU в итоговом составе новой группы и не указывайте в итоговом составе прежней. Оба изменения можно выполнить в одном файле.'],
            ['Проверка перед загрузкой', 'Проверьте, что все SKU существуют, действие выбрано из списка, а для «Создать» и «Изменить» заполнены код, название, оси и состав. Сохраните файл в формате XLSX и загрузите его через раздел «Товары» → «Группы вариантов».'],
        ];
    }

    private function instructionStyle(): Style
    {
        return (new Style)->setCellVerticalAlignment(CellVerticalAlignment::TOP)->setShouldWrapText();
    }

    /** @param list<string> $availableAxes
     * @param  list<string>  $availableIndividuals
     */
    private function addValidations(string $path, array $availableAxes, array $availableIndividuals): void
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
            $validations->setAttribute('count', '3');
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
            $axisValidation->appendChild($xml->createElement('formula1', '$AT$2:$AT$'.max(2, count($availableAxes) + 1)));
            $validations->appendChild($axisValidation);
            $individualValidation = $xml->createElement('dataValidation');
            foreach (['type' => 'list', 'allowBlank' => '1', 'showErrorMessage' => '1', 'showInputMessage' => '1', 'sqref' => 'Z2:AS5001'] as $key => $value) {
                $individualValidation->setAttribute($key, $value);
            }
            $individualValidation->appendChild($xml->createElement('formula1', '$AU$2:$AU$'.max(2, count($availableIndividuals) + 1)));
            $validations->appendChild($individualValidation);
            $root = $xml->documentElement;
            if ($root === null) {
                throw new RuntimeException('Не удалось подготовить шаблон Excel.');
            }
            $before = null;
            foreach ($root->childNodes as $child) {
                if (in_array($child->localName, ['hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks', 'colBreaks', 'drawing', 'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'tableParts', 'extLst'], true)) {
                    $before = $child;
                    break;
                }
            }
            $root->insertBefore($validations, $before);
            $this->addRoleHelperCells($xml, ['AT' => $availableAxes, 'AU' => $availableIndividuals]);
            $serialized = $xml->saveXML();
            if ($serialized === false) {
                throw new RuntimeException('Не удалось подготовить шаблон Excel.');
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', $serialized);
            $this->normalizeStylesForExcel($zip);
        } finally {
            $zip->close();
        }
    }

    /** @param array<string, list<string>> $choicesByColumn */
    private function addRoleHelperCells(DOMDocument $xml, array $choicesByColumn): void
    {
        $sheetData = $xml->getElementsByTagName('sheetData')->item(0);
        if ($sheetData === null) {
            throw new RuntimeException('Не удалось подготовить список осей в шаблоне Excel.');
        }
        $rows = [];
        foreach ($sheetData->childNodes as $row) {
            if ($row instanceof DOMElement && $row->localName === 'row') {
                $reference = $row->getAttribute('r');
                if (ctype_digit($reference)) {
                    $rows[(int) $reference] = $row;
                }
            }
        }
        foreach ($choicesByColumn as $columnName => $choices) {
            foreach ($choices as $index => $choice) {
                $rowNumber = $index + 2;
                $row = $rows[$rowNumber] ?? $xml->createElement('row');
                $row->setAttribute('spans', '1:47');
                if (! isset($rows[$rowNumber])) {
                    $row->setAttribute('r', (string) $rowNumber);
                    $sheetData->appendChild($row);
                    $rows[$rowNumber] = $row;
                }
                $cell = $xml->createElement('c');
                $cell->setAttribute('r', $columnName.$rowNumber);
                $cell->setAttribute('t', 'inlineStr');
                $inlineString = $xml->createElement('is');
                $text = $xml->createElement('t');
                $text->appendChild($xml->createTextNode($choice));
                $inlineString->appendChild($text);
                $cell->appendChild($inlineString);
                $row->appendChild($cell);
            }
        }
        $cols = $xml->getElementsByTagName('cols')->item(0);
        if ($cols !== null) {
            $column = $xml->createElement('col');
            foreach (['min' => '46', 'max' => '47', 'width' => '2', 'hidden' => '1', 'customWidth' => '1'] as $attribute => $value) {
                $column->setAttribute($attribute, $value);
            }
            $cols->appendChild($column);
        }
        $dimension = $xml->getElementsByTagName('dimension')->item(0);
        if ($dimension !== null) {
            $dimension->setAttribute('ref', 'A1:AU'.max(array_keys($rows) ?: [1]));
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
        $serialized = $xml->saveXML();
        if ($serialized === false) {
            throw new RuntimeException('Не удалось подготовить стили шаблона Excel.');
        }
        $zip->addFromString('xl/styles.xml', $serialized);
    }
}
