<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;

class CategoryOverviewResource extends CategoryResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'children' => self::collection($this->whenLoaded('children')),
            'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
            'attribute_groups' => AttributeGroupResource::collection($this->whenLoaded('attributeGroups')),
        ];
    }
}
