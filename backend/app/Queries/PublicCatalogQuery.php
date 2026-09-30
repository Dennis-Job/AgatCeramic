<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PublicCatalogQuery
{
    /** @return LengthAwarePaginator<int, Product> */
    public function products(): LengthAwarePaginator
    {
        return Product::query()->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category', 'brand', 'primaryImage'])->orderBy('name')->orderBy('id')->paginate(24);
    }

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return Category::query()->where('is_active', true)->with('image')->orderBy('sort_order')->orderBy('name')->get();
    }
}
