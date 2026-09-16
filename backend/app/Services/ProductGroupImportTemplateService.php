<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\ProductGroup;
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
    private const GROUP_HEADERS = ['Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название', 'Ось 1', 'Ось 2', 'Ось 3', 'Ось 4', 'Ось 5', 'Ось 6', 'Ось 7', 'Ось 8', 'Ось 9', 'Ось 10', 'Ось 11', 'Ось 12', 'Ось 13', 'Ось 14', 'Ось 15', 'Ось 16', 'Ось 17', 'Ось 18', 'Ось 19', 'Ось 20'];

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
            $availableAxisValues = Attribute::query()->whereNotIn('type', ['text', 'multiselect'])->orderBy('name')->orderBy('id')->get();
            $axisChoices = $this->axes->forAttributes($availableAxisValues);
            $writer->openToFile($path);
            $groupsSheet = $writer->getCurrentSheet();
            $groupsSheet->setName('Группы')->setSheetView((new SheetView)->setFreezeRow(2));
            $writer->addRow($this->row(self::GROUP_HEADERS, $this->headerStyle())->setHeight(36));
            foreach (range(1, count(self::GROUP_HEADERS)) as $column) {
                $groupsSheet->setColumnWidth($column <= 5 ? 24 : 18, $column);
            }
            ProductGroup::query()->with(['axes', 'products'])->orderBy('code')->get()->each(function (ProductGroup $group) use ($writer, $axisChoices): void {
                $axes = $group->axes->map(fn (Attribute $attribute): string => $axisChoices[(int) $attribute->id] ?? (string) $attribute->name)->all();
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
            ['Лист «Состав»', 'Для каждой создаваемой или изменяемой группы перечислите полный итоговый набор SKU: один SKU в строке. В группе должно быть от 2 до 500 SKU. Один SKU может входить только в одну итоговую группу.'],
            ['Перенос товара', 'Чтобы перенести товар между группами, укажите его SKU в итоговом составе новой группы и не указывайте в итоговом составе прежней. Оба изменения можно выполнить в одном файле.'],
            ['Проверка перед загрузкой', 'Проверьте, что все SKU существуют, действие выбрано из списка, а для «Создать» и «Изменить» заполнены код, название, оси и состав. Сохраните файл в формате XLSX и загрузите его через раздел «Товары» → «Группы вариантов».'],
        ];
    }

    private function instructionStyle(): Style
    {
        return (new Style)->setCellVerticalAlignment(CellVerticalAlignment::TOP)->setShouldWrapText();
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
            $this->addAxisHelperCells($xml, $availableAxes);
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

    /** @param list<string> $availableAxes */
    private function addAxisHelperCells(DOMDocument $xml, array $availableAxes): void
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
        $serialized = $xml->saveXML();
        if ($serialized === false) {
            throw new RuntimeException('Не удалось подготовить стили шаблона Excel.');
        }
        $zip->addFromString('xl/styles.xml', $serialized);
    }
}
