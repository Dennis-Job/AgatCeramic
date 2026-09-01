<?php

namespace App\Queries;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductQuery
{
    /** @param array<string, mixed> $filters */
    public function filtered(array $filters): Builder
    {
        $query = Product::query();

        if ($search = $filters['search'] ?? null) {
            $pattern = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(article_number) LIKE ?', [$pattern])
                    ->orWhere('barcode', 'LIKE', $pattern);
            });
        }

        foreach (['category_id', 'brand_id', 'is_active', 'is_on_sale'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (isset($filters['has_stock'])) {
            $filters['has_stock']
                ? $query->where('stock_quantity', '>', 0)
                : $query->where(fn (Builder $query) => $query->whereNull('stock_quantity')->orWhere('stock_quantity', 0));
        }

        if (isset($filters['price_from'])) {
            $query->where('price', '>=', $filters['price_from']);
        }

        if (isset($filters['price_to'])) {
            $query->where('price', '<=', $filters['price_to']);
        }

        return $query
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id');
    }
}
