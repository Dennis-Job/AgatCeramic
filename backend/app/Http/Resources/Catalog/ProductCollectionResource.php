<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Http\Resources\MediaResource;
use App\Models\ProductCollection;
use Illuminate\Http\Request;

/** @extends ApiResource<ProductCollection> */
class ProductCollectionResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'brand_id' => $this->brand_id, 'name' => $this->name, 'slug' => $this->slug,
            'headline' => $this->headline, 'description' => $this->description, 'image_id' => $this->image_id,
            'is_active' => $this->is_active,
            'brand' => new BrandResource($this->whenLoaded('brand')), 'image' => new MediaResource($this->whenLoaded('image')),
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at?->toAtomString(), 'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
