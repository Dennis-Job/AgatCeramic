<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

final class ProductImportTemplateCategoryValidator
{
    public function __construct(private readonly Filesystem $files) {}

    public function validate(Category $selected, string $path): void
    {
        $metadata = $this->metadata($path);
        if ($metadata['category_id'] === $selected->id) {
            return;
        }

        $currentName = Category::query()->whereKey($metadata['category_id'])->value('name');
        $name = is_string($currentName) ? $currentName : $metadata['category_name'];
        throw ValidationException::withMessages(['file' => [
            'Категория шаблона «'.$name.'» (ID '.$metadata['category_id'].') не совпадает с выбранной категорией «'.$selected->name.'» (ID '.$selected->id.'). Выберите категорию шаблона или скачайте шаблон выбранной категории.',
        ]]);
    }

    /** @return array{category_id: int, category_name: string} */
    private function metadata(string $path): array
    {
        $temporaryDirectory = sys_get_temp_dir().'/agat-category-'.bin2hex(random_bytes(16));
        $reader = null;
        try {
            if (! $this->files->makeDirectory($temporaryDirectory, 0700)) {
                throw new RuntimeException('Не удалось создать временный каталог проверки шаблона.');
            }
            $options = new Options;
            $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $options->setTempFolder($temporaryDirectory);
            $reader = new Reader($options);
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Справочники') {
                    continue;
                }
                // Do not iterate product rows or the remaining lookup rows during submission.
                foreach ($sheet->getRowIterator() as $row) {
                    return $this->parseMetadata($row->toArray());
                }
                break;
            }
            throw ValidationException::withMessages(['file' => ['Не удалось определить категорию шаблона. Скачайте актуальный шаблон; не изменяйте скрытый лист «Справочники».']]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => ['Не удалось прочитать XLSX-файл. Проверьте, что файл не повреждён.']]);
        } finally {
            try {
                $reader?->close();
            } catch (Throwable) {
            }
            // OpenSpout skips cache cleanup when open() fails partway through initialization.
            unset($sheet, $reader);
            $this->files->deleteDirectory($temporaryDirectory);
        }
    }

    /**
     * @param  list<mixed>  $values
     * @return array{category_id: int, category_name: string}
     */
    private function parseMetadata(array $values): array
    {
        $value = $values[1] ?? null;
        $categoryId = is_int($value) || is_string($value) || is_float($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $name = $values[2] ?? null;
        if (($values[0] ?? null) !== 'AGAT_CATEGORY_TEMPLATE_V1' || $categoryId === false || ! is_string($name) || trim($name) === '') {
            throw ValidationException::withMessages(['file' => ['Не удалось определить категорию шаблона. Скачайте актуальный шаблон; не изменяйте скрытый лист «Справочники».']]);
        }

        return ['category_id' => $categoryId, 'category_name' => trim($name)];
    }
}
