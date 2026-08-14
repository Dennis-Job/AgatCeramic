<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ProductVariantResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
