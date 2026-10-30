<?php

namespace App\Queries;

use App\Models\ProductCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductCollectionQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ProductCollection>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ProductCollection::query()->with(['brand.logo', 'brand.documents', 'image'])->withCount('products');
        foreach (['brand_id', 'is_active'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(static function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(slug) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return $query->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();
    }
}
