<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ProductResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku' => $this->sku,
            'article_number' => $this->article_number,
            'barcode' => $this->barcode,
            'unit' => $this->unit,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
            'is_on_sale' => $this->is_on_sale,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
            'group' => $this->whenLoaded('groupMembership', fn () => $this->groupMembership?->group === null ? null : [
                'id' => $this->groupMembership->group->id,
                'name' => $this->groupMembership->group->name,
                'code' => $this->groupMembership->group->code,
            ]),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
