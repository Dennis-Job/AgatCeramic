<?php

namespace App\Services;

use App\Models\ProductImageImport;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/** @phpstan-type Entry array{name: string, index: int, extension: string, size: int} */
class ProductImageImportArchive
{
    public const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    private const MAX_ENTRIES = 10000;

    private const MAX_UNCOMPRESSED_BYTES = 2_000_000_000;

    public function open(ProductImageImport $import): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open(Storage::disk($import->disk)->path($import->path)) !== true) {
            throw new RuntimeException('Не удалось открыть ZIP-архив.');
        }

        return $zip;
    }

    /** @return array<int|string, list<Entry>> */
    public function inspect(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new RuntimeException('В архиве слишком много файлов.');
        }
        $total = 0;
        $groups = [];
        $declaredFolders = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');
            $size = (int) ($stat['size'] ?? 0);
            if ($size > self::MAX_IMAGE_BYTES) {
                throw new RuntimeException('Размер изображения в архиве превышает 10 МБ.');
            }
            $total += $size;
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('Распакованный размер архива превышает допустимый лимит.');
            }
            if ($name === '' || str_contains($name, '\\') || str_contains($name, '..') || str_starts_with($name, '/')) {
                throw new RuntimeException('Архив содержит небезопасный путь.');
            }
            $operationsSystem = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($i, $operationsSystem, $attributes)
                && $operationsSystem === ZipArchive::OPSYS_UNIX
                && is_int($attributes)
                && (($attributes >> 16) & 0170000) === 0120000) {
                throw new RuntimeException('Архив не может содержать символические ссылки.');
            }
            // Finder metadata is not gallery content, but must pass the safety checks above.
            if ($this->isMacMetadata($name)) {
                continue;
            }
            if (str_ends_with($name, '/')) {
                if (substr_count(rtrim($name, '/'), '/') !== 0) {
                    throw new RuntimeException('Вложенные каталоги не поддерживаются.');
                }
                $declaredFolders[rtrim($name, '/')] = true;

                continue;
            }
            if (substr_count($name, '/') !== 1) {
                throw new RuntimeException('Файлы должны находиться непосредственно в папке SKU.');
            }
            [$sku, $file] = explode('/', $name, 2);
            if ($sku === '' || $file === '' || ! preg_match('/^'.preg_quote($sku, '/').'_(\d+)\.(jpe?g|png|webp)$/i', $file, $m) || (int) $m[1] < 1) {
                $groups[$sku ?: '__invalid__'][] = ['name' => $name, 'index' => $i, 'extension' => '', 'size' => $size];

                continue;
            }
            $groups[$sku][] = ['name' => $name, 'index' => $i, 'extension' => strtolower($m[2]) === 'jpeg' ? 'jpg' : strtolower($m[2]), 'size' => $size];
        }
        foreach (array_keys($declaredFolders) as $folder) {
            $groups[$folder] ??= [];
        }
        if ($groups === []) {
            throw new RuntimeException('ZIP-архив не содержит папок SKU.');
        }

        return $groups;
    }

    private function isMacMetadata(string $name): bool
    {
        if (str_starts_with($name, '__MACOSX/')) {
            return true;
        }
        if (str_ends_with($name, '/')) {
            return false;
        }
        $filename = basename($name);

        return $filename === '.DS_Store' || str_starts_with($filename, '._');
    }
}
