<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;

/** @extends ApiResource<Brand> */
class PublicCatalogBrandResource extends ApiResource
{
    /** @return array{id: int, name: string, slug: string} */
    #[\Override]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
    }
}
