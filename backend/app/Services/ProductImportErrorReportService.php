<?php

namespace App\Services;

use App\Models\ProductImport;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class ProductImportErrorReportService
{
    /** @return array{path: string, name: string} */
    public function create(ProductImport $import): array
    {
        $directory = storage_path('app/product-imports');
        File::ensureDirectoryExists($directory);
        $path = tempnam($directory, 'errors-');
        if ($path === false) {
            throw new RuntimeException('Не удалось подготовить XLSX-отчёт по ошибкам импорта.');
        }

        $writer = new Writer;
        try {
            $writer->openToFile($path);
            $writer->getCurrentSheet()->setName('Ошибки импорта');
            $writer->addRow(Row::fromValues(['Строка Excel', 'Товар', 'Ошибки']));
            foreach ($import->rowErrors()->get() as $error) {
                $writer->addRow(Row::fromValues([
                    $error->row_number,
                    $error->name ?: 'Без наименования',
                    implode("\n", array_filter($error->messages, static fn (mixed $message): bool => is_string($message))),
                ]));
            }
            $writer->close();
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return ['path' => $path, 'name' => 'product-import-'.$import->id.'-errors.xlsx'];
    }
}
