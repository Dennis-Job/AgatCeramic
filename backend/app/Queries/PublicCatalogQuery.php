<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PublicCatalogQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true));
        foreach (['category', 'brand'] as $relation) {
            if (is_string($filters[$relation] ?? null) && $filters[$relation] !== '') {
                $query->whereHas($relation, fn ($related) => $related->where('slug', $filters[$relation]));
            }
        }
        if (is_string($filters['collection'] ?? null) && $filters['collection'] !== '') {
            $query->whereHas('attributeValues', fn ($values) => $values
                ->whereHas('attribute', fn ($attribute) => $attribute->where('slug', 'kollektsiya')->where('is_visible_on_product_page', true))
                ->whereJsonContains('product_attribute_values.value', $filters['collection']));
        }

        return $query->with(['category', 'brand', 'primaryImage'])->orderBy('name')->orderBy('id')->paginate(24)->withQueryString();
    }

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return Category::query()->where('is_active', true)->with('image')->orderBy('sort_order')->orderBy('name')->get();
    }
}
