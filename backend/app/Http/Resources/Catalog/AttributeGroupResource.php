<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Models\AttributeGroup;
use Illuminate\Http\Request;

/** @extends ApiResource<AttributeGroup> */
class AttributeGroupResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description, 'sort_order' => $this->sort_order, 'created_at' => $this->created_at?->toAtomString(), 'updated_at' => $this->updated_at?->toAtomString()];
    }
}
