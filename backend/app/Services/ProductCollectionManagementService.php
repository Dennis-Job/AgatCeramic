<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Media;
use App\Models\ProductCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductCollectionManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MediaManagementService $mediaService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): ProductCollection
    {
        return DB::transaction(function () use ($actor, $attributes): ProductCollection {
            $attributes = $this->validate($attributes);
            Brand::query()->whereKey($attributes['brand_id'])->lockForUpdate()->firstOrFail();
            $this->assertUnique($attributes, null);
            $this->assertPublicImage($attributes['image_id'] ?? null);
            $collection = ProductCollection::query()->create($attributes);
            $this->auditLogService->record($actor, 'collection.created', $collection);

            return $collection->load(['brand.logo', 'brand.documents', 'image'])->loadCount('products');
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, ProductCollection $collection, array $attributes): ProductCollection
    {
        return DB::transaction(function () use ($actor, $collection, $attributes): ProductCollection {
            // Brand locks serialize both name uniqueness and deletion with collection writes.
            $brandIds = collect([$collection->brand_id, $attributes['brand_id'] ?? $collection->brand_id])->unique()->sort();
            Brand::query()->whereKey($brandIds)->orderBy('id')->lockForUpdate()->get();
            $collection = ProductCollection::query()->whereKey($collection->id)->lockForUpdate()->firstOrFail();
            if (! $brandIds->contains($collection->brand_id)) {
                throw ValidationException::withMessages(['brand_id' => ['The collection brand changed concurrently. Retry the request.']]);
            }
            $attributes = $this->validate([...$collection->only(['brand_id', 'name', 'slug', 'headline', 'description', 'image_id', 'is_active']), ...$attributes]);
            if ($attributes['brand_id'] !== $collection->brand_id && $collection->products()->exists()) {
                throw ValidationException::withMessages(['brand_id' => ['A collection assigned to products cannot change brand.']]);
            }
            $this->assertUnique($attributes, $collection->id);
            $this->assertPublicImage($attributes['image_id'] ?? null);
            $before = $this->auditLogService->capture($collection);
            $imageId = $collection->image_id;
            $collection->fill($attributes)->save();
            $this->auditLogService->recordUpdated($actor, 'collection.updated', $collection, $before);
            if ($imageId !== null && $collection->image_id !== $imageId) {
                $this->mediaService->deleteIfUnused($actor, $imageId);
            }

            return $collection->load(['brand.logo', 'brand.documents', 'image'])->loadCount('products');
        }, 3);
    }

    public function delete(User $actor, ProductCollection $collection): void
    {
        DB::transaction(function () use ($actor, $collection): void {
            $collection = ProductCollection::query()->whereKey($collection->id)->lockForUpdate()->firstOrFail();
            if ($collection->products()->exists()) {
                throw ValidationException::withMessages(['collection' => ['A collection assigned to products cannot be deleted.']]);
            }
            $imageId = $collection->image_id;
            $this->auditLogService->record($actor, 'collection.deleted', $collection);
            $collection->delete();
            if ($imageId !== null) {
                $this->mediaService->deleteIfUnused($actor, $imageId);
            }
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function validate(array $attributes): array
    {
        foreach (['name', 'slug', 'headline', 'description'] as $field) {
            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $attributes[$field] = trim($attributes[$field]);
            }
        }

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($attributes, [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'headline' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'],
            'image_id' => ['nullable', 'integer'], 'is_active' => ['sometimes', 'boolean'],
        ])->validate();

        return $validated;
    }

    /** @param array<string, mixed> $attributes */
    private function assertUnique(array $attributes, ?int $exceptId): void
    {
        $name = $attributes['name'];
        if (! is_string($name)) {
            throw new \LogicException('Validated collection name is invalid.');
        }
        $existing = ProductCollection::query()->where('brand_id', $attributes['brand_id'])->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))->get(['name', 'slug']);
        if ($existing->contains(fn (ProductCollection $collection): bool => mb_strtolower(trim($collection->name)) === mb_strtolower($name))) {
            throw ValidationException::withMessages(['name' => ['A collection with this name already exists for this brand.']]);
        }
        if ($existing->contains('slug', $attributes['slug'])) {
            throw ValidationException::withMessages(['slug' => ['A collection with this slug already exists for this brand.']]);
        }
    }

    private function assertPublicImage(mixed $imageId): void
    {
        if ($imageId === null) {
            return;
        }
        $image = Media::query()->whereKey($imageId)->lockForUpdate()->first();
        if ($image === null || $image->kind !== 'image' || $image->disk !== 'public') {
            throw ValidationException::withMessages(['image_id' => ['Select an existing public image.']]);
        }
    }
}
