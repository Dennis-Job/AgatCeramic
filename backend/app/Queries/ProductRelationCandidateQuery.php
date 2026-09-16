<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\ProductRelation;
use Illuminate\Database\Eloquent\Collection;

class ProductRelationCandidateQuery
{
    /** @return Collection<int, Product> */
    public function get(Product $product, ?string $search, int $limit): Collection
    {
        $excludedIds = $product->outgoingRelations()->pluck('related_product_id')
            ->merge(ProductRelation::query()->where('related_product_id', $product->id)->pluck('product_id'))
            ->push($product->id)
            ->unique();

        $query = Product::query()
            ->with(['category', 'brand', 'primaryImage'])
            ->whereNotIn('id', $excludedIds);

        if (is_string($search) && $search !== '') {
            $pattern = '%'.mb_strtolower($search).'%';
            $query->where(fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$pattern]));
        }

        return $query->orderBy('name')->limit($limit)->get();
    }
}
