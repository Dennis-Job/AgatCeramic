<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductImageManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly StorageCleanupService $storageCleanupService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, Product $product, UploadedFile $file, array $attributes): ProductImage
    {
        $path = null;

        try {
            return DB::transaction(function () use ($actor, $product, $file, $attributes, &$path): ProductImage {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $ordinal = $this->nextOrdinal($product);
                $baseName = "{$product->sku}_{$ordinal}";
                $directory = "product-images/{$product->id}";
                $fileName = "{$baseName}.{$this->extensionFor($file)}";
                $path = $file->storePubliclyAs($directory, $fileName, 'public');
                if (! is_string($path)) {
                    throw new RuntimeException('Unable to store the product image.');
                }

                $isPrimary = ($attributes['is_primary'] ?? false) || ! $product->images()->exists();
                if ($isPrimary) {
                    $product->images()->update(['is_primary' => false]);
                }

                $image = $product->images()->create([
                    ...Arr::except($attributes, ['alt', 'is_primary']),
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'alt' => $baseName,
                    'is_primary' => $isPrimary,
                ]);
                $this->auditLogService->record($actor, 'product.image-uploaded', $image);

                return $image;
            });
        } catch (\Throwable $exception) {
            if (is_string($path)) {
                $this->storageCleanupService->schedule('public', $path);
            }
            throw $exception;
        }
    }

    private function nextOrdinal(Product $product): int
    {
        $pattern = '/^'.preg_quote($product->sku, '/').'_(\d+)\.[^.]+$/';
        $ordinal = $product->images()
            ->pluck('path')
            ->map(function (string $path) use ($pattern): int {
                return preg_match($pattern, basename($path), $matches) === 1 ? (int) $matches[1] : 0;
            })
            ->max() + 1;

        while (Storage::disk('public')->exists("product-images/{$product->id}/{$product->sku}_{$ordinal}.jpg")
            || Storage::disk('public')->exists("product-images/{$product->id}/{$product->sku}_{$ordinal}.png")
            || Storage::disk('public')->exists("product-images/{$product->id}/{$product->sku}_{$ordinal}.webp")) {
            $ordinal++;
        }

        return $ordinal;
    }

    private function extensionFor(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported product image type.'),
        };
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Product $product, ProductImage $image, array $attributes): ProductImage
    {
        return DB::transaction(function () use ($actor, $product, $image, $attributes): ProductImage {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $image = $product->images()->findOrFail($image->id);
            $isPrimary = $attributes['is_primary'] ?? $image->is_primary;
            if ($isPrimary) {
                $product->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
            } elseif ($image->is_primary && ! $product->images()->whereKeyNot($image->id)->exists()) {
                $isPrimary = true;
            } elseif ($image->is_primary) {
                $replacement = $product->images()->whereKeyNot($image->id)->orderBy('sort_order')->orderBy('id')->first();
                $image->fill([...Arr::except($attributes, ['is_primary']), 'is_primary' => false])->save();
                $replacement?->update(['is_primary' => true]);
                $this->auditLogService->record($actor, 'product.image-updated', $image);

                return $image;
            }

            $image->fill([...Arr::except($attributes, ['is_primary']), 'is_primary' => $isPrimary])->save();
            $this->auditLogService->record($actor, 'product.image-updated', $image);

            return $image;
        });
    }

    public function delete(User $actor, Product $product, ProductImage $image): void
    {
        $image = DB::transaction(function () use ($actor, $product, $image): ProductImage {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $image = $product->images()->findOrFail($image->id);
            $replacement = $image->is_primary
                ? $product->images()->whereKeyNot($image->id)->orderBy('sort_order')->orderBy('id')->first()
                : null;
            $this->auditLogService->record($actor, 'product.image-deleted', $image);
            $this->storageCleanupService->schedule($image->disk, $image->path);
            $image->delete();
            $replacement?->update(['is_primary' => true]);

            return $image;
        });

    }
}
