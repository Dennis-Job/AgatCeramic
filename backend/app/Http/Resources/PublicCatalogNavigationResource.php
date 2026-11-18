<?php

namespace App\Http\Resources;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCatalogNavigationResource extends JsonResource
{
    /** @param array{brand: Brand|null, total: int, categories: list<array{category: Category, product_count: int}>} $navigation */
    public function __construct(private readonly array $navigation)
    {
        parent::__construct($navigation);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'brand' => $this->navigation['brand'] !== null ? (new PublicCatalogBrandResource($this->navigation['brand']))->resolve($request) : null,
            'total' => $this->navigation['total'],
            'categories' => array_map(static fn (array $entry): array => [
                'id' => $entry['category']->id,
                'name' => $entry['category']->name,
                'slug' => $entry['category']->slug,
                'parent_id' => $entry['category']->parent_id,
                'product_count' => $entry['product_count'],
            ], $this->navigation['categories']),
        ];
    }
}
