<?php

namespace App\Queries;

use App\Models\ProductCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicCollectionQuery
{
    public function get(string $brand, string $slug): ProductCollection
    {
        return ProductCollection::query()->published($brand)->where('slug', $slug)->with(['brand', 'image'])->firstOrFail();
    }

    /** @return LengthAwarePaginator<int, ProductCollection> */
    public function listing(): LengthAwarePaginator
    {
        return ProductCollection::query()->published()->with(['brand', 'image'])->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();
    }
}
