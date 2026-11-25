<?php

namespace App\Queries;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

class ProductQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{active: int, hidden: int, sale: int, regular: int}
     */
    public function filterCounts(array $filters): array
    {
        $activity = isset($filters['is_active']) ? (bool) $filters['is_active'] : null;
        $sale = isset($filters['is_on_sale']) ? (bool) $filters['is_on_sale'] : null;
        unset($filters['is_active'], $filters['is_on_sale']);

        /** @var object{active: int|numeric-string, hidden: int|numeric-string, sale: int|numeric-string, regular: int|numeric-string} $counts */
        $counts = $this->matching($filters)->toBase()->selectRaw(<<<'SQL'
            COUNT(*) FILTER (WHERE is_active AND is_on_sale = COALESCE(?, is_on_sale)) AS active,
            COUNT(*) FILTER (WHERE NOT is_active AND is_on_sale = COALESCE(?, is_on_sale)) AS hidden,
            COUNT(*) FILTER (WHERE is_on_sale AND is_active = COALESCE(?, is_active)) AS sale,
            COUNT(*) FILTER (WHERE NOT is_on_sale AND is_active = COALESCE(?, is_active)) AS regular
            SQL, [$sale, $sale, $activity, $activity])->firstOrFail();

        return [
            'active' => (int) $counts->active,
            'hidden' => (int) $counts->hidden,
            'sale' => (int) $counts->sale,
            'regular' => (int) $counts->regular,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function maximumImageCount(array $filters): int
    {
        $maximumImageCount = ProductImage::query()
            ->whereIn('product_id', $this->filtered($filters)->reorder()->select('products.id'))
            ->selectRaw('COUNT(*) as image_count')
            ->groupBy('product_id')
            ->orderByDesc('image_count')
            ->value('image_count');

        return is_int($maximumImageCount) ? $maximumImageCount : 0;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function filtered(array $filters): Builder
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        return $this->matching($filters)
            ->orderBy(is_string($sort) ? $sort : 'created_at', $direction === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    private function matching(array $filters): Builder
    {
        $query = Product::query();

        if (array_key_exists('skus', $filters)) {
            $query->whereIn('sku', $filters['skus']);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $this->applySearch($query, $search);
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

        return $query;
    }

    /** @param Builder<Product> $query */
    private function applySearch(Builder $query, string $search): void
    {
        $pattern = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $query) use ($pattern): void {
            $query->whereRaw('LOWER(name) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(sku) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(article_number) LIKE ?', [$pattern])
                ->orWhere('barcode', 'LIKE', $pattern);

            $collections = 'SELECT id FROM product_collections WHERE LOWER(name) LIKE ?';

            // The array lets PostgreSQL combine the collection index with trigram scans.
            // Short patterns use IN to avoid forcing a full trigram index scan.
            if ($query->getQuery()->getGrammar() instanceof PostgresGrammar && preg_match('/[\p{L}\p{N}]{3}/u', $pattern) === 1) {
                $query->orWhereRaw('collection_id = ANY (ARRAY('.$collections.'))', [$pattern]);
            } else {
                $query->orWhereRaw('collection_id IN ('.$collections.')', [$pattern]);
            }
        });
    }
}
