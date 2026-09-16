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

final class ProductPriceStatusErrorReportService
{
    /** @return array{path: string, name: string} */
    public function create(ProductImport $import): array
    {
        $path = tempnam(storage_path('app'), 'price-status-errors-');
        if ($path === false) {
            throw new RuntimeException('Не удалось создать отчёт об ошибках.');
        }
        $writer = new Writer;
        try {
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Ошибки');
            $writer->addRow($this->row(['Лист', 'Строка', 'SKU', 'Изменения', 'Ошибки'], $this->headerStyle()));
            foreach ($import->rowErrors()->get() as $error) {
                $values = $error->values ?? [];
                $messages = $error->messages;
                $writer->addRow($this->row([
                    $values['sheet'] ?? '—', $values['row'] ?? '—', $values['sku'] ?? $error->name,
                    json_encode($values['updates'] ?? [], JSON_UNESCAPED_UNICODE),
                    implode('; ', array_map($this->stringValue(...), $messages)),
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

        return ['path' => $path, 'name' => "product-price-status-import-{$import->id}-errors.xlsx"];
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
}
