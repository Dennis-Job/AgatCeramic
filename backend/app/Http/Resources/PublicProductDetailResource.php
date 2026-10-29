<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class PublicProductDetailResource extends PublicProductCardResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $logo = $this->brand?->logo;
        if (is_array($data['brand'])) {
            $data['brand'] = [...$data['brand'],
                'logo_url' => $logo?->disk === 'public' ? Storage::disk('public')->url($logo->path) : null,
                'logo_alt' => $logo?->alt ?: $this->brand?->name,
            ];
        }
        $attributes = $this->attributeValues->sortBy(fn (ProductAttributeValue $value): array => [$value->attribute->sort_order ?? 0, $value->attribute_id])->values();
        $collection = $attributes->first(fn (ProductAttributeValue $value): bool => $value->attribute?->slug === 'kollektsiya');
        $collectionValue = $collection?->getAttribute('value');

        return [...$data,
            'sku' => $this->sku, 'article_number' => $this->article_number, 'description' => $this->description,
            'images' => $this->images->map(fn ($image): array => [
                'id' => $image->id, 'url' => Storage::disk('public')->url($image->path),
                'alt' => $image->alt ?: $this->name, 'is_primary' => $image->is_primary, 'sort_order' => $image->sort_order,
            ]),
            'attributes' => PublicProductAttributeResource::collection($attributes),
            'collection' => is_string($collectionValue) && trim($collectionValue) !== '' && $this->brand !== null
                ? ['name' => $collectionValue, 'brand_slug' => $this->brand->slug] : null,
            'group' => $this->groupData($this->groupMembership?->group, $request),
            'related' => $this->relatedCards('related'), 'recommended' => $this->relatedCards('recommended'),
        ];
    }

    /** @return array<string, mixed>|null */
    private function groupData(?ProductGroup $group, Request $request): ?array
    {
        if ($group === null) {
            return null;
        }
        $axisIds = $group->axes->pluck('id');

        return [
            'axes' => $group->axes->map(fn ($axis): array => ['id' => $axis->id, 'slug' => $axis->slug, 'name' => $axis->name, 'type' => $axis->type]),
            'products' => $group->products->map(function (Product $product) use ($axisIds, $request): array {
                return [...(new PublicProductCardResource($product))->resolve($request),
                    'axis_values' => $product->attributeValues->whereIn('attribute_id', $axisIds)->values()->map(function (ProductAttributeValue $value) use ($request): array {
                        $data = (new PublicProductAttributeResource($value))->resolve($request);

                        return ['slug' => $data['slug'], 'value' => $data['value'], 'display_value' => $data['display_value']];
                    }),
                ];
            }),
        ];
    }

    private function relatedCards(string $type): AnonymousResourceCollection
    {
        return PublicProductCardResource::collection($this->outgoingRelations->where('type', $type)->pluck('relatedProduct')->values());
    }
}
