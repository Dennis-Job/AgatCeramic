<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AttributeResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attribute_group_id' => $this->attribute_group_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'unit' => $this->unit,
            'is_filterable' => $this->is_filterable,
            'is_required' => $this->is_required,
            'is_visible_on_product_page' => $this->is_visible_on_product_page,
            'sort_order' => $this->sort_order,
            'category_sort_order' => $this->when($this->pivot !== null, fn (): int => $this->pivot->sort_order),
            'options' => AttributeOptionResource::collection($this->whenLoaded('options')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
