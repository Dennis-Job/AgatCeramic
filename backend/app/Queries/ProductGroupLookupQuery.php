<?php

namespace App\Queries;

use App\Models\ProductGroup;
use Illuminate\Database\Eloquent\Collection;

class ProductGroupLookupQuery
{
    /** @return Collection<int, ProductGroup> */
    public function get(string $search): Collection
    {
        $query = ProductGroup::query()->select(['id', 'name', 'code']);

        if ($search !== '') {
            $literal = strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']);
            $pattern = '%'.$literal.'%';

            $query->where(fn ($groups) => $groups
                ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$pattern]));
        }

        return $query->orderBy('name')->orderBy('id')->limit(25)->get();
    }
}
