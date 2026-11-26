<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;
use ZipArchive;

final class ProductImportTemplateValidationWriter
{
    /**
     * Add SpreadsheetML features to the workbook streamed by OpenSpout.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, array<int, array{0: mixed, 1: mixed}>>  $lists
     * @param  array<string, int>  $listIndexes
     */
    public function apply(string $path, array $headers, array $lists, array $listIndexes): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть шаблон Excel.');
        }
        try {
            $workbook = $this->readPart($zip, 'xl/workbook.xml', 'Не удалось прочитать структуру шаблона Excel.');
            $this->hideLookupSheet($workbook);
            $sheet = $this->readPart($zip, 'xl/worksheets/sheet1.xml', 'Не удалось прочитать лист товаров шаблона Excel.');
            $styles = $this->readPart($zip, 'xl/styles.xml', 'Не удалось прочитать стили шаблона Excel.');

            $this->normalizeStyles($styles);
            $textStyleIndex = $this->appendTextCellFormat($styles);
            $this->formatIdentifierColumns($sheet, $headers, $textStyleIndex);
            $this->addDropdowns($workbook, $sheet, $headers, $lists, $listIndexes);

            $workbookXml = $workbook->saveXML();
            $sheetXml = $sheet->saveXML();
            $stylesXml = $styles->saveXML();
            if (! is_string($workbookXml) || ! is_string($sheetXml) || ! is_string($stylesXml)) {
                throw new RuntimeException('Не удалось сохранить структуру шаблона Excel.');
            }
            $zip->addFromString('xl/workbook.xml', $workbookXml);
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
            $zip->addFromString('xl/styles.xml', $stylesXml);
        } finally {
            $zip->close();
        }
    }

    private function readPart(ZipArchive $zip, string $path, string $errorMessage): DOMDocument
    {
        $document = new DOMDocument;
        $source = $zip->getFromName($path);
        if (! is_string($source) || ! $document->loadXML($source, LIBXML_NONET)) {
            throw new RuntimeException($errorMessage);
        }

        return $document;
    }

    private function hideLookupSheet(DOMDocument $workbook): void
    {
        $lookupSheet = $workbook->getElementsByTagName('sheet')->item(1);
        if (! $lookupSheet instanceof DOMElement) {
            throw new RuntimeException('Не удалось прочитать лист справочников шаблона Excel.');
        }
        $lookupSheet->setAttribute('state', 'hidden');
    }

    private function normalizeStyles(DOMDocument $styles): void
    {
        // Excel expects ARGB colors and the SpreadsheetML font element order.
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
    }

    private function appendTextCellFormat(DOMDocument $styles): int
    {
        $cellFormats = $styles->getElementsByTagName('cellXfs')->item(0);
        if (! $cellFormats instanceof DOMElement) {
            throw new RuntimeException('Не удалось прочитать форматы ячеек шаблона Excel.');
        }
        $textStyleIndex = $cellFormats->getElementsByTagName('xf')->length;
        $baseFormat = $cellFormats->getElementsByTagName('xf')->item(0);
        if (! $baseFormat instanceof DOMNode) {
            throw new RuntimeException('Не удалось прочитать текстовый формат шаблона Excel.');
        }
        $textFormat = $baseFormat->cloneNode(true);
        if (! $textFormat instanceof DOMElement) {
            throw new RuntimeException('Не удалось скопировать текстовый формат шаблона Excel.');
        }
        $textFormat->setAttribute('numFmtId', '49'); // Built-in Excel text format (@).
        $textFormat->setAttribute('applyNumberFormat', '1');
        $cellFormats->appendChild($textFormat);
        $cellFormats->setAttribute('count', (string) ($textStyleIndex + 1));

        return $textStyleIndex;
    }

    /** @param array<string, string> $headers */
    private function formatIdentifierColumns(DOMDocument $sheet, array $headers, int $textStyleIndex): void
    {
        $headerKeys = array_keys($headers);
        foreach (['article_number', 'barcode'] as $key) {
            $target = array_search($key, $headerKeys, true) + 1;
            foreach ($sheet->getElementsByTagName('col') as $columnStyle) {
                $minimum = (int) $columnStyle->getAttribute('min');
                $maximum = (int) $columnStyle->getAttribute('max');
                if ($minimum > $target || $maximum < $target) {
                    continue;
                }
                $this->isolateColumn($columnStyle, $target, $minimum, $maximum);
                $columnStyle->setAttribute('style', (string) $textStyleIndex);
                break;
            }
        }
    }

    private function isolateColumn(DOMElement $columnStyle, int $target, int $minimum, int $maximum): void
    {
        if ($minimum < $target) {
            $before = $columnStyle->cloneNode(true);
            if ($before instanceof DOMElement && $columnStyle->parentNode !== null) {
                $before->setAttribute('max', (string) ($target - 1));
                $columnStyle->parentNode->insertBefore($before, $columnStyle);
            }
        }
        if ($maximum > $target) {
            $after = $columnStyle->cloneNode(true);
            if ($after instanceof DOMElement && $columnStyle->parentNode !== null) {
                $after->setAttribute('min', (string) ($target + 1));
                $columnStyle->parentNode->insertBefore($after, $columnStyle->nextSibling);
            }
        }
        $columnStyle->setAttribute('min', (string) $target);
        $columnStyle->setAttribute('max', (string) $target);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, array<int, array{0: mixed, 1: mixed}>>  $lists
     * @param  array<string, int>  $listIndexes
     */
    private function addDropdowns(DOMDocument $workbook, DOMDocument $sheet, array $headers, array $lists, array $listIndexes): void
    {
        $workbookRoot = $workbook->documentElement;
        if ($workbookRoot === null) {
            throw new RuntimeException('Не удалось прочитать структуру шаблона Excel.');
        }
        $sheetRoot = $sheet->documentElement;
        if ($sheetRoot === null) {
            throw new RuntimeException('Не удалось прочитать лист товаров шаблона Excel.');
        }

        $definedNames = $workbook->createElement('definedNames');
        $validations = $sheet->createElement('dataValidations');
        $validations->setAttribute('count', (string) count($lists));
        $headerKeys = array_keys($headers);
        $index = 0;
        foreach ($lists as $key => $list) {
            $name = 'AgatList'.(++$index);
            $column = $this->column($listIndexes[$key] * 2 + 1);
            $defined = $workbook->createElement('definedName');
            $defined->setAttribute('name', $name);
            $defined->appendChild($workbook->createTextNode("'Справочники'!\${$column}\$4:\${$column}\$".(max(1, count($list)) + 3)));
            $definedNames->appendChild($defined);

            $target = $this->column(array_search($key, $headerKeys, true) + 1);
            $validations->appendChild($this->createListValidation($sheet, $target, $name));
        }
        $workbookRoot->appendChild($definedNames);
        $this->insertDataValidations($sheetRoot, $validations);
    }

    private function createListValidation(DOMDocument $sheet, string $column, string $name): DOMElement
    {
        $validation = $sheet->createElement('dataValidation');
        foreach ([
            'type' => 'list',
            'errorStyle' => 'stop',
            'allowBlank' => '1',
            'showErrorMessage' => '1',
            'showInputMessage' => '1',
            'showDropDown' => '0',
            'sqref' => $column.'2:'.$column.(ProductImportService::MAX_ROWS + 1),
            'errorTitle' => 'Выберите значение из списка',
            'error' => 'Допустимы только значения справочника.',
            'promptTitle' => 'Значение из справочника',
            'prompt' => 'Выберите значение в выпадающем списке.',
        ] as $attribute => $value) {
            $validation->setAttribute($attribute, $value);
        }
        $validation->appendChild($sheet->createElement('formula1', $name));

        return $validation;
    }

    private function insertDataValidations(DOMElement $sheetRoot, DOMElement $validations): void
    {
        // OpenSpout may emit legacyDrawing even on sheets without comments.
        $trailingElements = [
            'hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks',
            'colBreaks', 'customProperties', 'cellWatches', 'ignoredErrors', 'smartTags', 'drawing',
            'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'webPublishItems',
            'tableParts', 'extLst',
        ];
        $before = null;
        foreach ($sheetRoot->childNodes as $child) {
            if (in_array($child->localName, $trailingElements, true)) {
                $before = $child;
                break;
            }
        }
        $sheetRoot->insertBefore($validations, $before);
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
}
