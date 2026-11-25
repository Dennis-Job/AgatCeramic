<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImageImport;
use Throwable;
use ZipArchive;

/** @phpstan-import-type Entry from ProductImageImportArchive */
class ProductImageImportService
{
    private const BATCH_SIZE = 20;

    public function __construct(
        private readonly ProductImageImportArchive $archive,
        private readonly ProductImageImportStager $stager,
        private readonly ProductImageImportGallery $gallery,
        private readonly StorageCleanupService $cleanup,
    ) {}

    public function process(ProductImageImport $import): bool
    {
        $import->refresh();
        if (in_array($import->status, ['completed', 'failed'], true)) {
            return true;
        }
        $zip = $this->archive->open($import);
        try {
            $groups = $this->archive->inspect($zip);
            $import->forceFill(['total_folders' => count($groups)])->save();
            $pending = array_diff_key($groups, array_fill_keys($import->processedSkus(), true));
            // Each gallery advances its checkpoint atomically. A new job resumes this batch from the database.
            foreach (array_slice($pending, 0, self::BATCH_SIZE, true) as $sku => $entries) {
                $this->processSku($import, $zip, (string) $sku, $entries);
            }

            return count($pending) <= self::BATCH_SIZE
                || array_diff_key($groups, array_fill_keys($import->refresh()->processedSkus(), true)) === [];
        } finally {
            $zip->close();
        }
    }

    /** @param list<Entry> $entries */
    private function processSku(ProductImageImport $import, ZipArchive $zip, string $sku, array $entries): void
    {
        $product = Product::query()->where('sku', $sku)->first();
        $validation = $this->stager->validate($zip, $sku, $entries, $product !== null);
        if ($product === null || $validation['errors'] !== []) {
            $this->gallery->reject($import, $sku, $entries[0]['name'] ?? null, $validation['errors']);

            return;
        }

        $reservations = [];
        $published = false;
        try {
            $images = $this->stager->stage($zip, $product->id, $import->id, $sku, $validation['ordinals'], $reservations);
            $published = $this->gallery->apply($import, $product->id, $sku, $images, $reservations);
        } catch (Throwable) {
            $this->gallery->reject($import, $sku, null, ['Не удалось применить изображения этой папки.']);
        } finally {
            if (! $published && $reservations !== []) {
                $this->cleanup->releaseReservations($reservations);
            }
        }
    }
}
