<?php

namespace App\Queries;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class PublicProductQuery
{
    public function find(string $slug): Product
    {
        return Product::query()->where('slug', $slug)->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with([
                'category', 'brand.logo', 'primaryImage',
                'images' => fn (Relation $query) => $query->getQuery()->where('disk', 'public')->orderBy('sort_order')->orderBy('id'),
                'attributeValues' => fn (Relation $query) => $query->getQuery()->whereHas('attribute', fn ($attribute) => $attribute->where('is_visible_on_product_page', true)),
                'attributeValues.attribute.options',
                'groupMembership.group.axes' => fn (Relation $query) => $query->getQuery()->where('is_visible_on_product_page', true),
                'groupMembership.group.products' => fn (Relation $query) => $query->getQuery()->where('is_active', true)->where('is_on_sale', true)
                    ->whereHas('category', fn ($category) => $category->where('is_active', true))->orderBy('products.id'),
                'groupMembership.group.products.category', 'groupMembership.group.products.brand',
                'groupMembership.group.products.primaryImage',
                'groupMembership.group.products.attributeValues' => fn (Relation $query) => $query->getQuery()->whereHas('attribute', fn ($attribute) => $attribute->where('is_visible_on_product_page', true)),
                'groupMembership.group.products.attributeValues.attribute.options',
                'outgoingRelations' => fn (Relation $query) => $query->getQuery()->whereHas('relatedProduct', fn (Builder $related) => $related
                    ->where('is_active', true)->where('is_on_sale', true)
                    ->whereHas('category', fn ($category) => $category->where('is_active', true)))
                    ->orderBy('sort_order')->orderBy('id'),
                'outgoingRelations.relatedProduct.category', 'outgoingRelations.relatedProduct.brand',
                'outgoingRelations.relatedProduct.primaryImage',
            ])->firstOrFail();
    }
}
