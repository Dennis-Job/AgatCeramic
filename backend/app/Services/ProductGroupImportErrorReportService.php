<?php

namespace App\Services;

use App\Models\ProductImport;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

final class ProductGroupImportErrorReportService
{
    /** @return array{path: string, name: string} */
    public function create(ProductImport $import): array
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
                $values = $error->values;
                $messages = $error->messages;
                $writer->addRow($this->row([
                    $this->cellValue($values['sheet'] ?? null),
                    $this->cellValue($values['row'] ?? null),
                    $this->cellValue($values['name'] ?? $error->name),
                    implode('; ', array_values(array_filter($messages, 'is_string'))),
                ]));
            }
            $writer->close();
        } catch (Throwable $exception) {
            try {
                $writer->close();
            } catch (Throwable) {
            }
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => "product-group-import-{$import->id}-errors.xlsx"];
    }

    private function cellValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param list<bool|DateTimeInterface|float|int|string|null> $values */
    private function row(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn ($value) => is_string($value) ? new StringCell($value, null) : Cell::fromValue($value), $values), $style);
    }

    private function headerStyle(): Style
    {
        return (new Style)->setFontBold()->setFontColor('FFFFFF')->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER)->setBackgroundColor('23456B')->setShouldWrapText();
    }
}
