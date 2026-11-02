<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** @extends ApiResource<Product> */
class PublicProductCardResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $image = $this->primaryImage;
        $category = $this->category;

        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'price' => $this->price, 'old_price' => $this->old_price, 'unit' => $this->unit,
            'is_on_sale' => $this->is_on_sale,
            'in_stock' => $this->stock_quantity !== null && $this->stock_quantity > 0,
            'image_url' => $image?->disk === 'public' ? Storage::disk('public')->url($image->path) : null,
            'image_alt' => $image?->alt ?: $this->name,
            'category' => $category === null ? null : ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug],
            'brand' => $this->brand === null ? null : ['id' => $this->brand->id, 'name' => $this->brand->name, 'slug' => $this->brand->slug],
            'size' => $this->sizeLabel($request),
        ];
    }

    private function sizeLabel(Request $request): ?string
    {
        $value = $this->attributeValues->first(fn (ProductAttributeValue $value): bool => $value->attribute?->slug === 'razmer' && $value->attribute->is_visible_on_product_page);
        if ($value === null) {
            return null;
        }
        $data = (new PublicProductAttributeResource($value))->resolve($request);
        $label = is_string($data['display_value']) ? trim($data['display_value']) : '';
        if ($label === '') {
            return null;
        }
        $unit = is_string($data['unit']) ? trim($data['unit']) : '';

        return $unit === '' ? $label : $label.' '.$unit;
    }
}
