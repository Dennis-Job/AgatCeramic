<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;

/** @extends ApiResource<Attribute> */
class AttributeResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
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
            'is_required' => $this->isRequired(),
            'is_visible_on_product_page' => $this->is_visible_on_product_page,
            'sort_order' => $this->sort_order,
            'category_sort_order' => $this->when($this->pivot() !== null, fn (): int => $this->categorySortOrder()),
            'options' => AttributeOptionResource::collection($this->whenLoaded('options')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }

    private function pivot(): ?Pivot
    {
        if (! $this->resource instanceof Attribute) {
            return null;
        }

        if (! $this->resource->relationLoaded('pivot')) {
            return null;
        }

        $pivot = $this->resource->getRelation('pivot');

        return $pivot instanceof Pivot ? $pivot : null;
    }

    private function isRequired(): bool
    {
        return in_array($this->pivot()?->getAttribute('is_required'), [true, 1, '1'], true);
    }

    private function categorySortOrder(): int
    {
        $value = $this->pivot()?->getAttribute('sort_order');

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
    }
}
