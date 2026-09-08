<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageImport;
use App\Models\ProductImageImportError;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ProductImageImportService
{
    private const MAX_ENTRIES = 10000;

    private const MAX_UNCOMPRESSED_BYTES = 2_000_000_000;

    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    private const MIME_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly AuditLogService $audit, private readonly StorageCleanupService $cleanup) {}

    public function process(ProductImageImport $import): bool
    {
        $archivePath = Storage::disk($import->disk)->path($import->path);
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Не удалось открыть ZIP-архив.');
        }
        try {
            $groups = $this->inspect($zip);
            $import->forceFill(['total_folders' => count($groups)])->save();
            $completed = array_fill_keys($import->processed_skus ?? [], true);
            $pending = array_filter($groups, fn (array $entries, string $sku): bool => ! isset($completed[$sku]), ARRAY_FILTER_USE_BOTH);
            // A bounded batch keeps every queue attempt below its timeout. The SKU
            // checkpoint is committed with its gallery update, so a retry resumes.
            $pending = array_slice($pending, 0, 20, true);
            foreach ($pending as $sku => $entries) {
                $this->processSku($import, $zip, $sku, $entries);
            }

            return count($completed) + count($pending) >= count($groups);
        } finally {
            $zip->close();
        }
    }

    /** @return array<string, list<array{name:string,index:int,extension:string,size:int}>> */
    private function inspect(ZipArchive $zip): array
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
                && (($attributes >> 16) & 0170000) === 0120000) {
                throw new RuntimeException('Архив не может содержать символические ссылки.');
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

    /** @param list<array{name:string,index:int,extension:string,size:int}> $entries */
    private function processSku(ProductImageImport $import, ZipArchive $zip, string $sku, array $entries): void
    {
        $messages = [];
        $ordinals = [];
        if ($entries === []) {
            $messages[] = 'Папка SKU не содержит изображений.';
        }
        foreach ($entries as $entry) {
            if (! preg_match('/^'.preg_quote($sku, '/').'_(\d+)\./', basename($entry['name']), $m)) {
                $messages[] = "Имя {$entry['name']} не соответствует папке SKU.";

                continue;
            }
            $ordinal = (int) $m[1];
            if (isset($ordinals[$ordinal])) {
                $messages[] = "Дублирующий номер изображения {$ordinal}.";
            }
            $ordinals[$ordinal] = $entry;
        }
        $product = Product::query()->where('sku', $sku)->first();
        if ($product === null) {
            $messages[] = 'Товар с таким SKU не найден.';
        }
        foreach ($entries as $entry) {
            $stream = $zip->getStream($entry['name']);
            $data = $stream === false ? false : stream_get_contents($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $mime = is_string($data) ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) : false;
            if (! is_string($data) || ! isset(self::MIME_TYPES[$mime]) || self::MIME_TYPES[$mime] !== $entry['extension']) {
                $messages[] = "Файл {$entry['name']} не является допустимым изображением заявленного типа.";
            }
        }
        if ($messages !== []) {
            $this->failSku($import, $sku, $entries[0]['name'] ?? null, $messages);

            return;
        }
        $created = 0;
        $replaced = 0;
        $written = [];
        $backups = [];
        $staged = [];
        try {
            foreach ($ordinals as $ordinal => $entry) {
                $stream = $zip->getStream($entry['name']);
                $data = $stream === false ? false : stream_get_contents($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (! is_string($data)) {
                    throw new RuntimeException('Не удалось прочитать файл из ZIP-архива.');
                }
                $staged[$ordinal] = "product-image-import-staging/{$import->id}/".uniqid('', true);
                if (! Storage::disk($import->disk)->put($staged[$ordinal], $data)) {
                    throw new RuntimeException('Не удалось подготовить изображение к импорту.');
                }
            }
            DB::transaction(function () use ($import, $product, $sku, $ordinals, $staged, &$created, &$replaced, &$written, &$backups): void {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $existing = $product->images()->get()->keyBy(function (ProductImage $image) use ($sku): int {
                    return preg_match('/^'.preg_quote($sku, '/').'_(\\d+)(?:-i\\d+)?\\.[^.]+$/', basename($image->path), $m) ? (int) $m[1] : -$image->id;
                });
                if (isset($ordinals[1])) {
                    $product->images()->where('is_primary', true)->update(['is_primary' => false]);
                }
                foreach ($ordinals as $ordinal => $entry) {
                    $data = Storage::disk($import->disk)->get($staged[$ordinal]);
                    if (! is_string($data)) {
                        throw new RuntimeException('Не удалось прочитать подготовленное изображение.');
                    }
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
                    $extension = self::MIME_TYPES[$mime] ?? throw new RuntimeException('Недопустимое изображение.');
                    $path = "product-images/{$product->id}/{$sku}_{$ordinal}-i{$import->id}.{$extension}";
                    $old = $existing->get($ordinal);
                    if ($old !== null && Storage::disk($old->disk)->exists($old->path)) {
                        $backups[$old->path] = [
                            'disk' => $old->disk,
                            'contents' => Storage::disk($old->disk)->get($old->path),
                        ];
                    }
                    if (! Storage::disk('public')->put($path, $data)) {
                        throw new RuntimeException('Не удалось сохранить изображение.');
                    }
                    $written[] = $path;
                    if ($old !== null) {
                        $oldPath = $old->path;
                        $old->update(['path' => $path, 'mime_type' => $mime, 'size' => strlen($data), 'alt' => "{$sku}_{$ordinal}", 'sort_order' => $ordinal, 'is_primary' => (int) $ordinal === 1]);
                        if ($oldPath !== $path) {
                            $this->cleanup->schedule($old->disk, $oldPath);
                        }
                        $replaced++;
                    } else {
                        $product->images()->create(['disk' => 'public', 'path' => $path, 'mime_type' => $mime, 'size' => strlen($data), 'alt' => "{$sku}_{$ordinal}", 'sort_order' => $ordinal, 'is_primary' => (int) $ordinal === 1]);
                        $created++;
                    }
                }
                if (isset($ordinals[1])) {
                    $product->images()
                        ->where('path', "product-images/{$product->id}/{$sku}_1-i{$import->id}.{$ordinals[1]['extension']}")
                        ->update(['is_primary' => true]);
                }
                $import->increment('processed_folders');
                $import->increment('created_images', $created);
                $import->increment('replaced_images', $replaced);
                $processed = $import->processed_skus ?? [];
                if (! in_array($sku, $processed, true)) {
                    $import->forceFill(['processed_skus' => [...$processed, $sku]])->save();
                }
                $this->audit->record($import->user, 'product.image-imported', $product, ['import_id' => $import->id, 'created' => $created, 'replaced' => $replaced]);
            });
            foreach ($staged as $path) {
                $this->cleanup->schedule($import->disk, $path);
            }
        } catch (\Throwable $exception) {
            foreach ($backups as $path => $backup) {
                Storage::disk($backup['disk'])->put($path, $backup['contents']);
            }
            foreach ($written as $path) {
                if (! isset($backups[$path])) {
                    $this->cleanup->schedule('public', $path);
                }
            }
            foreach ($staged as $path) {
                $this->cleanup->schedule($import->disk, $path);
            }
            $this->failSku($import, $sku, null, ['Не удалось применить изображения этой папки.']);
        }
    }

    /** @param list<string> $messages */
    private function failSku(ProductImageImport $import, string $sku, ?string $entry, array $messages): void
    {
        DB::transaction(function () use ($import, $sku, $entry, $messages): void {
            $import = ProductImageImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if (in_array($sku, $import->processed_skus ?? [], true)) {
                return;
            }
            ProductImageImportError::query()->create(['product_image_import_id' => $import->id, 'sku' => mb_substr($sku, 0, 255), 'entry' => $entry, 'messages' => $messages]);
            $import->increment('failed_folders');
            $import->increment('processed_folders');
            $import->forceFill(['processed_skus' => [...($import->processed_skus ?? []), $sku]])->save();
        });
    }
}
