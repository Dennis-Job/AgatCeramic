<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;
use ZipArchive;

final class ProductImportTemplateValidationWriter
{
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

    /** OpenSpout streams cells; this adds standard OOXML list validation without materializing 5000 empty rows. */
    /**
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
            $workbook = new DOMDocument;
            $workbookSource = $zip->getFromName('xl/workbook.xml');
            $sheetSource = $zip->getFromName('xl/worksheets/sheet1.xml');
            $stylesSource = $zip->getFromName('xl/styles.xml');
            if (! is_string($workbookSource) || ! $workbook->loadXML($workbookSource, LIBXML_NONET) || $workbook->documentElement === null) {
                throw new RuntimeException('Не удалось прочитать структуру шаблона Excel.');
            }
            $workbookRoot = $workbook->documentElement;
            $sheets = $workbook->getElementsByTagName('sheet');
            $lookupSheet = $sheets->item(1);
            if (! $lookupSheet instanceof DOMElement) {
                throw new RuntimeException('Не удалось прочитать лист справочников шаблона Excel.');
            }
            $lookupSheet->setAttribute('state', 'hidden');
            $definedNames = $workbook->createElement('definedNames');
            $sheet = new DOMDocument;
            if (! is_string($sheetSource) || ! $sheet->loadXML($sheetSource, LIBXML_NONET) || $sheet->documentElement === null) {
                throw new RuntimeException('Не удалось прочитать лист товаров шаблона Excel.');
            }
            $sheetRoot = $sheet->documentElement;
            $styles = new DOMDocument;
            if (! is_string($stylesSource) || ! $styles->loadXML($stylesSource, LIBXML_NONET)) {
                throw new RuntimeException('Не удалось прочитать стили шаблона Excel.');
            }
            // Normalize OpenSpout styles to the SpreadsheetML schema used by Excel.
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
            foreach (['article_number', 'barcode'] as $key) {
                $target = array_search($key, array_keys($headers), true) + 1;
                foreach ($sheet->getElementsByTagName('col') as $columnStyle) {
                    $minimum = (int) $columnStyle->getAttribute('min');
                    $maximum = (int) $columnStyle->getAttribute('max');
                    if ($minimum > $target || $maximum < $target) {
                        continue;
                    }
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
                    $columnStyle->setAttribute('style', (string) $textStyleIndex);
                    break;
                }
            }
            $validations = $sheet->createElement('dataValidations');
            $validations->setAttribute('count', (string) count($lists));
            $index = 0;
            foreach ($lists as $key => $list) {
                $name = 'AgatList'.($index + 1);
                $column = $this->column($listIndexes[$key] * 2 + 1);
                $defined = $workbook->createElement('definedName');
                $defined->setAttribute('name', $name);
                $defined->appendChild($workbook->createTextNode("'Справочники'!\${$column}\$4:\${$column}\$".(max(1, count($list)) + 3)));
                $definedNames->appendChild($defined);
                $target = $this->column(array_search($key, array_keys($headers), true) + 1);
                $validation = $sheet->createElement('dataValidation');
                foreach (['type' => 'list', 'errorStyle' => 'stop', 'allowBlank' => '1', 'showErrorMessage' => '1', 'showInputMessage' => '1', 'showDropDown' => '0', 'sqref' => $target.'2:'.$target.(ProductImportService::MAX_ROWS + 1), 'errorTitle' => 'Выберите значение из списка', 'error' => 'Допустимы только значения справочника.', 'promptTitle' => 'Значение из справочника', 'prompt' => 'Выберите значение в выпадающем списке.'] as $attribute => $value) {
                    $validation->setAttribute($attribute, $value);
                }
                $validation->appendChild($sheet->createElement('formula1', $name));
                $validations->appendChild($validation);
                $index++;
            }
            $workbookRoot->appendChild($definedNames);
            // Every trailing worksheet element must follow dataValidations, including
            // the legacyDrawing emitted by OpenSpout even on sheets without comments.
            $before = null;
            foreach ($sheetRoot->childNodes as $child) {
                if (in_array($child->localName, ['hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks', 'colBreaks', 'customProperties', 'cellWatches', 'ignoredErrors', 'smartTags', 'drawing', 'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'webPublishItems', 'tableParts', 'extLst'], true)) {
                    $before = $child;
                    break;
                }
            }
            $sheetRoot->insertBefore($validations, $before);
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
}
