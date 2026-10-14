<?php

namespace App\Queries;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BrandQuery
{
    /** @return LengthAwarePaginator<int, Brand> */
    public function paginate(string $search, int $page): LengthAwarePaginator
    {
        $query = Brand::query()->with(['logo', 'documents']);

        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(static function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(slug) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return $query->orderBy('name')->orderBy('id')->paginate(25, page: $page)
            ->appends(['search' => $search]);
    }
}
