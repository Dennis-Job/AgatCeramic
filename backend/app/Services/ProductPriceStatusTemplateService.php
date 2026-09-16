<?php

namespace App\Services;

use DateTimeInterface;
use DOMDocument;
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

final class ProductPriceStatusTemplateService
{
    /** @return array{path: string, name: string} */
    public function createTemplate(): array
    {
        $path = tempnam(storage_path('app'), 'price-status-template-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать файл шаблона.');
        }
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            foreach (ProductPriceStatusWorkbookReader::HEADERS as $sheetName => $headers) {
                $sheet = $writer->getCurrentSheet();
                $sheet->setName($sheetName)->setSheetView((new SheetView)->setFreezeRow(2));
                foreach (array_keys($headers) as $index) {
                    $sheet->setColumnWidth($index === 0 ? 26 : 20, $index + 1);
                }
                $writer->addRow($this->row($headers, $this->headerStyle())->setHeight(36));
                if ($sheetName !== 'Распродажа') {
                    $writer->addNewSheetAndMakeItCurrent();
                }
            }
            $writer->close();
            $this->addBooleanValidation($path);
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => 'product-price-status-template.xlsx'];
    }

    /** @param list<mixed> $values */
    private function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (mixed $value): Cell => is_string($value) ? new StringCell($value, null) : Cell::fromValue($this->cellScalar($value)), $values), $style);
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_object($value) && method_exists($value, '__toString') ? (string) $value : '';
    }

    private function cellScalar(mixed $value): bool|DateTimeInterface|\DateInterval|float|int|string|null
    {
        if (is_bool($value) || $value instanceof DateTimeInterface || $value instanceof \DateInterval || is_float($value) || is_int($value) || is_string($value) || $value === null) {
            return $value;
        }

        return $this->stringValue($value);
    }

    private function headerStyle(): Style
    {
        return (new Style)->setFontBold()->setFontColor('FFFFFF')->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER)->setBackgroundColor('23456B')->setShouldWrapText();
    }

    private function addBooleanValidation(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть шаблон Excel.');
        }
        try {
            foreach ([2, 3] as $sheetNumber) {
                $xml = new DOMDocument;
                $source = $zip->getFromName("xl/worksheets/sheet{$sheetNumber}.xml");
                if (! is_string($source) || ! $xml->loadXML($source, LIBXML_NONET) || $xml->documentElement === null) {
                    throw new RuntimeException('Не удалось прочитать структуру шаблона Excel.');
                }
                $validations = $xml->createElement('dataValidations');
                $validations->setAttribute('count', '1');
                $validation = $xml->createElement('dataValidation');
                foreach (['type' => 'list', 'allowBlank' => '1', 'showErrorMessage' => '1', 'showInputMessage' => '1', 'sqref' => 'B2:B5001'] as $key => $value) {
                    $validation->setAttribute($key, $value);
                }
                $validation->appendChild($xml->createElement('formula1', '"Да,Нет"'));
                $validations->appendChild($validation);
                // SpreadsheetML requires dataValidations before trailing elements such as
                // pageMargins and legacyDrawing. Appending it after OpenSpout's legacyDrawing
                // makes Excel repair the workbook on open.
                $before = null;
                foreach ($xml->documentElement->childNodes as $child) {
                    if (in_array($child->localName, ['hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks', 'colBreaks', 'customProperties', 'cellWatches', 'ignoredErrors', 'smartTags', 'drawing', 'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'webPublishItems', 'tableParts', 'extLst'], true)) {
                        $before = $child;
                        break;
                    }
                }
                $xml->documentElement->insertBefore($validations, $before);
                $contents = $xml->saveXML();
                if (! is_string($contents)) {
                    throw new RuntimeException('Не удалось сохранить структуру шаблона Excel.');
                }
                $zip->addFromString("xl/worksheets/sheet{$sheetNumber}.xml", $contents);
            }
        } finally {
            $zip->close();
        }
    }
}
