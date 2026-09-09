<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Models\Category;
use Illuminate\Http\Request;

/** @extends ApiResource<Category> */
class CategoryResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku_prefix' => $this->sku_prefix,
            'is_parent' => $this->is_parent,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
