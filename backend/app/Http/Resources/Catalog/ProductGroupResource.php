<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ProductGroupResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'axes' => $this->whenLoaded('axes', fn () => $this->axes->map(fn ($axis) => [
                'id' => $axis->id, 'name' => $axis->name, 'slug' => $axis->slug, 'type' => $axis->type,
            ])),
            'products' => $this->whenLoaded('products', function () use ($request) {
                $axisIds = $this->axes->pluck('id');

                return $this->products->map(fn ($product) => [
                    ...(new ProductResource($product))->resolve($request),
                    'axis_values' => ProductAttributeValueResource::collection(
                        $product->attributeValues->whereIn('attribute_id', $axisIds)->values(),
                    )->resolve($request),
                ]);
            }),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
