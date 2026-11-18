<?php

namespace App\Queries;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublicBrandQuery
{
    public function get(string $slug): Brand
    {
        return Brand::query()->where('slug', $slug)->where('is_active', true)->with('logo')->firstOrFail();
    }

    /** @return LengthAwarePaginator<int, Brand> */
    public function listing(): LengthAwarePaginator
    {
        return Brand::query()->where('is_active', true)->with('logo')->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();
    }
}
