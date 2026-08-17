<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ProductVariantAttributeValueResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'attribute_id' => $this->attribute_id,
            'value' => $this->value,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
        ];
    }
}
