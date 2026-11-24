<?php

namespace App\Http\Resources;

use App\Models\Brand;
use App\Models\ProductCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** @extends ApiResource<ProductCollection> */
class PublicCollectionResource extends ApiResource
{
    public function __construct(private readonly ProductCollection $collection)
    {
        parent::__construct($collection);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $brand = $this->collection->brand ?? throw (new ModelNotFoundException)->setModel(Brand::class);

        return [...self::details($this->collection, $brand), 'brand_name' => $brand->name];
    }

    /** @return array{id: int, name: string, slug: string, brand_slug: string, headline: string|null, description: string|null, country_code: string|null, image_url: string|null, image_alt: string|null} */
    public static function details(ProductCollection $collection, Brand $brand): array
    {
        $image = $collection->image;
        $publicImage = $image?->disk === 'public' && $image->kind === 'image' && str_starts_with($image->mime_type, 'image/');

        return [
            'id' => $collection->id, 'name' => $collection->name, 'slug' => $collection->slug,
            'brand_slug' => $brand->slug, 'headline' => $collection->headline, 'description' => $collection->description,
            'country_code' => $brand->country_code,
            'image_url' => $publicImage ? Storage::disk('public')->url($image->path) : null,
            'image_alt' => $publicImage ? ($image->alt ?: $collection->name) : null,
        ];
    }
}
