<?php

namespace App\Http\Resources;

use App\Models\Slider;
use Illuminate\Http\Request;

/** @extends ApiResource<Slider> */
class SliderResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_published' => $this->is_published,
            'banners' => BannerResource::collection($this->whenLoaded('banners')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
