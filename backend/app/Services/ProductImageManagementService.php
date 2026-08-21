<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductImageManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly StorageCleanupService $storageCleanupService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, Product $product, UploadedFile $file, array $attributes): ProductImage
    {
        $path = $file->storePublicly("product-images/{$product->id}", 'public');

        try {
            return DB::transaction(function () use ($actor, $product, $file, $attributes, $path): ProductImage {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $isPrimary = ($attributes['is_primary'] ?? false) || ! $product->images()->exists();
                if ($isPrimary) {
                    $product->images()->update(['is_primary' => false]);
                }

                $image = $product->images()->create([
                    ...Arr::except($attributes, ['is_primary']),
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'is_primary' => $isPrimary,
                ]);
                $this->auditLogService->record($actor, 'product.image-uploaded', $image);

                return $image;
            });
        } catch (\Throwable $exception) {
            $this->storageCleanupService->schedule('public', $path);
            throw $exception;
        }
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
