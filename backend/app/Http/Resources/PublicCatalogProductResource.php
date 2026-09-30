<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** @extends ApiResource<Product> */
class PublicCatalogProductResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $category = $this->category;
        if ($category === null) {
            throw new \LogicException('Public catalog product must belong to an active category.');
        }
        $image = $this->primaryImage;

        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description,
            'price' => $this->price, 'old_price' => $this->old_price, 'unit' => $this->unit, 'is_on_sale' => $this->is_on_sale,
            'category' => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug],
            'brand' => $this->brand === null ? null : ['id' => $this->brand->id, 'name' => $this->brand->name, 'slug' => $this->brand->slug],
            'image_url' => $image?->disk === 'public' ? Storage::disk('public')->url($image->path) : null,
            'image_alt' => $image === null ? $this->name : ($image->alt ?? $this->name),
        ];
    }
}
