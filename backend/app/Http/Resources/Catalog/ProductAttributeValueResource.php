<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ProductAttributeValueResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'attribute_id' => $this->attribute_id,
            'value' => $this->value,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
