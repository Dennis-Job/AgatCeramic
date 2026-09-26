<?php

namespace App\Http\Resources;

use App\Models\Banner;
use Illuminate\Http\Request;

/** @extends ApiResource<Banner> */
class BannerResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'link_label' => $this->link_label,
            'link_url' => $this->link_url,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
