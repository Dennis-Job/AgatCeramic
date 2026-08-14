<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductImageManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, Product $product, UploadedFile $file, array $attributes): ProductImage
    {
        $path = $file->storePublicly("product-images/{$product->id}", 'public');

        try {
            return DB::transaction(function () use ($actor, $product, $file, $attributes, $path): ProductImage {
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
            Storage::disk('public')->delete($path);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Product $product, ProductImage $image, array $attributes): ProductImage
    {
        return DB::transaction(function () use ($actor, $product, $image, $attributes): ProductImage {
            $isPrimary = $attributes['is_primary'] ?? $image->is_primary;
            if ($isPrimary) {
                $product->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
            } elseif ($image->is_primary && ! $product->images()->whereKeyNot($image->id)->exists()) {
                $isPrimary = true;
            } elseif ($image->is_primary) {
                $product->images()->whereKeyNot($image->id)->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
            }

            $image->fill([...Arr::except($attributes, ['is_primary']), 'is_primary' => $isPrimary])->save();
            $this->auditLogService->record($actor, 'product.image-updated', $image);

            return $image;
        });
    }

    public function delete(User $actor, Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($actor, $product, $image): void {
            if ($image->is_primary) {
                $product->images()->whereKeyNot($image->id)->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
            }
            $this->auditLogService->record($actor, 'product.image-deleted', $image);
            $image->delete();
        });

        Storage::disk($image->disk)->delete($image->path);
    }
}
