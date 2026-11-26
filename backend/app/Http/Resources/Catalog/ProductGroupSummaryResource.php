<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Models\ProductGroup;
use Illuminate\Http\Request;

/** @extends ApiResource<ProductGroup> */
class ProductGroupSummaryResource extends ApiResource
{
    /** @return array{id: int, name: string, code: string} */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
        ];
    }
}
