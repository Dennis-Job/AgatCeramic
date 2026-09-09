<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use App\Models\AttributeOption;
use Illuminate\Http\Request;

/** @extends ApiResource<AttributeOption> */
class AttributeOptionResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'value' => $this->value, 'label' => $this->label, 'sort_order' => $this->sort_order];
    }
}
