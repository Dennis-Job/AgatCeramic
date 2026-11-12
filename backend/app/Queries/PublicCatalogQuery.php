<?php

namespace App\Queries;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class PublicCatalogQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(array $filters = []): LengthAwarePaginator
    {
        $query = $this->scope($filters);
        foreach (['brand'] as $relation) {
            if (is_string($filters[$relation] ?? null) && $filters[$relation] !== '') {
                $query->whereHas($relation, fn ($related) => $related->where('slug', $filters[$relation]));
            }
        }
        if (is_string($filters['collection'] ?? null) && $filters['collection'] !== '') {
            $query->whereHas('collection', fn ($collection) => $collection->where('is_active', true)->where('name', $filters['collection']));
        }
        foreach (['price_from' => '>=', 'price_to' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->where('price', $operator, $filters[$key]);
            }
        }
        if ($filters['in_stock'] ?? false) {
            $query->where('stock_quantity', '>', 0);
        }
        $raw = $filters['attributes'] ?? null;
        if (is_string($raw)) {
            $attributes = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            $category = $filters['category'] ?? null;
            $definitions = $this->filterAttributes(is_string($category) ? $category : null)->keyBy('id');
            if (is_array($attributes)) {
                foreach ($attributes as $id => $filter) {
                    $attribute = $definitions->get((int) $id);
                    if ($attribute !== null && is_array($filter)) {
                        (new PublicCatalogAttributeQuery)->apply($query, $attribute, $filter);
                    }
                }
            }
        }
        match ($filters['sort'] ?? 'name_asc') {
            'price_asc' => $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END')->orderBy('price'),
            'price_desc' => $query->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END')->orderByDesc('price'),
            'name_desc' => $query->orderByDesc('name'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderBy('name'),
        };

        return $query->with([
            'category', 'brand', 'primaryImage',
            'attributeValues' => fn (Relation $query) => $query->getQuery()->whereHas('attribute', fn ($attribute) => $attribute->where('slug', 'razmer')->where('is_visible_on_product_page', true)),
            'attributeValues.attribute.options',
        ])->orderBy('id')->paginate(24)->withQueryString();
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Product>
     */
    public function scope(array $filters): Builder
    {
        $query = Product::query()->where('is_active', true)->whereHas('category', fn ($category) => $category->where('is_active', true));
        if (is_string($filters['category'] ?? null) && $filters['category'] !== '') {
            if ($filters['category_descendants'] ?? false) {
                // Traverse every ancestor; publication still depends only on the product's own category.
                $query->whereRaw('category_id IN (WITH RECURSIVE subtree AS (SELECT id FROM categories WHERE slug = ? AND is_active = ? UNION SELECT c.id FROM categories c JOIN subtree s ON c.parent_id = s.id) SELECT id FROM subtree)', [$filters['category'], true]);
            } else {
                $query->whereHas('category', fn ($category) => $category->where('slug', $filters['category']));
            }
        }

        return $query;
    }

    /** @return Collection<int, Attribute> */
    public function filterAttributes(?string $category): Collection
    {
        if ($category === null || $category === '') {
            return new Collection;
        }

        return Attribute::query()->where('is_filterable', true)->where('slug', '!=', 'kollektsiya')
            ->whereHas('categories', fn ($query) => $query->where('slug', $category)->where('is_active', true))
            ->with('options')->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function filters(array $filters): array
    {
        $scope = $this->scope($filters);
        $brands = Brand::query()->whereIn('id', (clone $scope)->select('brand_id'))->orderBy('name')->orderBy('id')->get(['id', 'name', 'slug']);
        $collections = ProductCollection::query()->where('is_active', true)->whereIn('id', (clone $scope)->select('collection_id'))
            ->whereHas('brand', fn ($brand) => $brand->where('slug', $filters['brand'] ?? null))
            ->with('brand')->orderBy('name')->orderBy('id')->get();
        $category = $filters['category'] ?? null;
        $attributes = $this->filterAttributes(is_string($category) ? $category : null);
        $options = (new PublicCatalogAttributeQuery)->options($scope, $attributes);

        return [
            'brands' => $brands->map(fn (Brand $brand) => ['id' => $brand->id, 'name' => $brand->name, 'slug' => $brand->slug])->all(),
            'collections' => $collections->map(fn (ProductCollection $collection) => ['id' => $collection->id, 'name' => $collection->name, 'brand_slug' => $collection->brand?->slug])->all(),
            'attributes' => $attributes->map(fn (Attribute $attribute) => [
                'id' => $attribute->id, 'name' => $attribute->name, 'slug' => $attribute->slug, 'type' => $attribute->type, 'unit' => $attribute->unit,
                'options' => $options[$attribute->id],
            ])->all(),
        ];
    }

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return Category::query()->where('is_active', true)->with('image')->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
    }
}
