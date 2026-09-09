<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Models\Brand;
use Illuminate\Http\Request;

/** @extends ApiResource<Brand> */
class BrandResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description, 'country_code' => $this->country_code, 'is_active' => $this->is_active, 'created_at' => $this->created_at?->toAtomString(), 'updated_at' => $this->updated_at?->toAtomString()];
    }
}
