<?php

namespace App\Services;

use finfo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * @phpstan-import-type Entry from ProductImageImportArchive
 *
 * @phpstan-type StagedImage array{path: string, mime_type: string, size: int}
 */
class ProductImageImportStager
{
    private const MIME_TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public function __construct(private readonly StorageCleanupService $cleanup) {}

    /**
     * @param  list<Entry>  $entries
     * @return array{errors: list<string>, ordinals: array<int, Entry>}
     */
    public function validate(ZipArchive $zip, string $sku, array $entries, bool $productExists): array
    {
        $errors = $entries === [] ? ['Папка SKU не содержит изображений.'] : [];
        $ordinals = [];
        $pattern = '/^'.preg_quote($sku, '/').'_(\d+)\./';
        foreach ($entries as $entry) {
            if (! preg_match($pattern, basename($entry['name']), $matches)) {
                $errors[] = "Имя {$entry['name']} не соответствует папке SKU.";

                continue;
            }
            $ordinal = (int) $matches[1];
            if (isset($ordinals[$ordinal])) {
                $errors[] = "Дублирующий номер изображения {$ordinal}.";
            }
            $ordinals[$ordinal] = $entry;
        }
        if (! $productExists) {
            $errors[] = 'Товар с таким SKU не найден.';
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($entries as $entry) {
            $data = $this->read($zip, $entry);
            $expectedMime = self::MIME_TYPES[$entry['extension']] ?? null;
            if ($data === false || $expectedMime === null || $fileInfo->buffer($data) !== $expectedMime) {
                $errors[] = "Файл {$entry['name']} не является допустимым изображением заявленного типа.";
            }
        }

        return ['errors' => $errors, 'ordinals' => $ordinals];
    }

    /**
     * @param  array<int, Entry>  $ordinals
     * @param  list<int>  $reservations  Cleanup IDs, including writes that fail after creating a file.
     * @return array<int, StagedImage>
     */
    public function stage(ZipArchive $zip, int $productId, int $importId, string $sku, array $ordinals, array &$reservations): array
    {
        $directory = "product-images/{$productId}/".Str::uuid();
        $disk = Storage::disk('public');
        // The queue job times out after 80 seconds. Abandoned files become eligible for scheduler cleanup.
        $expiresAt = now()->addMinutes(10);
        $images = [];
        foreach ($ordinals as $ordinal => $entry) {
            $data = $this->read($zip, $entry);
            if ($data === false) {
                throw new RuntimeException('Не удалось прочитать файл из ZIP-архива.');
            }
            $extension = $entry['extension'];
            $path = "{$directory}/{$sku}_{$ordinal}-i{$importId}.{$extension}";
            $reservations[] = $this->cleanup->reserve('public', $path, $expiresAt);
            if (! $disk->put($path, $data)) {
                throw new RuntimeException('Не удалось сохранить изображение.');
            }
            $images[$ordinal] = ['path' => $path, 'mime_type' => self::MIME_TYPES[$extension], 'size' => strlen($data)];
        }

        return $images;
    }

    /** @param Entry $entry */
    private function read(ZipArchive $zip, array $entry): string|false
    {
        $stream = $zip->getStreamIndex($entry['index']);
        if ($stream === false) {
            return false;
        }
        try {
            $data = stream_get_contents($stream, ProductImageImportArchive::MAX_IMAGE_BYTES + 1);

            return is_string($data) && strlen($data) === $entry['size'] ? $data : false;
        } finally {
            fclose($stream);
        }
    }
}
