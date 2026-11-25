<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageImport;
use App\Models\ProductImageImportError;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** @phpstan-import-type StagedImage from ProductImageImportStager */
class ProductImageImportGallery
{
    public function __construct(private readonly AuditLogService $audit, private readonly StorageCleanupService $cleanup) {}

    /**
     * @param  array<int, StagedImage>  $images
     * @param  list<int>  $reservations
     */
    public function apply(ProductImageImport $import, int $productId, string $sku, array $images, array $reservations): bool
    {
        return DB::transaction(function () use ($import, $productId, $sku, $images, $reservations): bool {
            $locked = ProductImageImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($this->alreadyProcessed($locked, $sku)) {
                return false;
            }
            $product = Product::query()->whereKey($productId)->where('sku', $sku)->lockForUpdate()->firstOrFail();
            if (! $this->cleanup->cancelReservations($reservations)) {
                throw new RuntimeException('Истёк срок хранения подготовленных изображений.');
            }

            $gallery = $product->images()->orderBy('id')->get();
            $before = $this->galleryState($gallery);
            $replaced = $this->replaceImages($product, $sku, $gallery, $images);
            $created = count($images) - $replaced;
            $this->checkpoint($locked, $sku, $created, $replaced, 0);

            $after = $this->galleryState($product->images()->orderBy('id')->get());
            $this->audit->record($import->user, 'product.image-imported', $product, [
                'import_id' => $locked->id,
                'created' => $created,
                'replaced' => $replaced,
                'changes' => $before === $after ? [] : ['images' => ['before' => $before, 'after' => $after]],
            ]);

            return true;
        });
    }

    /** @param list<string> $messages */
    public function reject(ProductImageImport $import, string $sku, ?string $entry, array $messages): void
    {
        DB::transaction(function () use ($import, $sku, $entry, $messages): void {
            $locked = ProductImageImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($this->alreadyProcessed($locked, $sku)) {
                return;
            }
            ProductImageImportError::query()->create([
                'product_image_import_id' => $locked->id,
                'sku' => mb_substr($sku, 0, 255),
                'entry' => $entry,
                'messages' => $messages,
            ]);
            $this->checkpoint($locked, $sku, 0, 0, 1);
        });
    }

    /**
     * @param  Collection<int, ProductImage>  $gallery
     * @param  array<int, StagedImage>  $images
     */
    private function replaceImages(Product $product, string $sku, Collection $gallery, array $images): int
    {
        $pattern = '/^'.preg_quote($sku, '/').'_(\d+)(?:-i\d+)?\.[^.]+$/';
        $existing = $gallery->keyBy(fn (ProductImage $image): int => preg_match($pattern, basename($image->path), $matches)
            ? (int) $matches[1]
            : -$image->id);
        if (isset($images[1])) {
            $primary = $product->images()->where('is_primary', true);
            $replacementPrimary = $existing->get(1);
            if ($replacementPrimary !== null) {
                $primary->whereKeyNot($replacementPrimary->id);
            }
            $primary->update(['is_primary' => false]);
        }

        $replaced = 0;
        foreach ($images as $ordinal => $image) {
            $attributes = [
                ...$image,
                'disk' => 'public',
                'alt' => "{$sku}_{$ordinal}",
                'sort_order' => $ordinal,
                'is_primary' => $ordinal === 1,
            ];
            $old = $existing->get($ordinal);
            if ($old === null) {
                $product->images()->create($attributes);

                continue;
            }
            $this->cleanup->schedule($old->disk, $old->path);
            $old->update($attributes);
            $replaced++;
        }

        return $replaced;
    }

    /**
     * @param  Collection<int, ProductImage>  $gallery
     * @return list<array{image_id: int, file: string, mime_type: string, size: int, is_primary: bool, sort_order: int}>
     */
    private function galleryState(Collection $gallery): array
    {
        return array_values($gallery->map(fn (ProductImage $image): array => [
            'image_id' => $image->id,
            'file' => basename($image->path),
            'mime_type' => $image->mime_type,
            'size' => $image->size,
            'is_primary' => $image->is_primary,
            'sort_order' => $image->sort_order,
        ])->all());
    }

    private function alreadyProcessed(ProductImageImport $import, string $sku): bool
    {
        return in_array($import->status, ['completed', 'failed'], true)
            || in_array($sku, $import->processedSkus(), true);
    }

    private function checkpoint(ProductImageImport $import, string $sku, int $created, int $replaced, int $failed): void
    {
        $import->forceFill([
            'processed_skus' => [...$import->processedSkus(), $sku],
            'processed_folders' => $import->processed_folders + 1,
            'created_images' => $import->created_images + $created,
            'replaced_images' => $import->replaced_images + $replaced,
            'failed_folders' => $import->failed_folders + $failed,
        ])->save();
    }
}
