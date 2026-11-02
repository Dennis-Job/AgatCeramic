<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PublicCatalogProductResource extends PublicProductCardResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        if ($this->category === null) {
            throw new \LogicException('Public catalog product must belong to an active category.');
        }
        $data = parent::toArray($request);
        $image = $this->primaryImage;

        return [...$data,
            'description' => $this->description,
            'image_alt' => $image === null ? $this->name : ($image->alt ?? $this->name),
        ];
    }
}
