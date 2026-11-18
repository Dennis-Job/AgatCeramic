<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** @extends ApiResource<Brand> */
class PublicBrandResource extends ApiResource
{
    /** @return array{id: int, name: string, slug: string, description: string|null, logo_url: string|null, logo_alt: string} */
    #[\Override]
    public function toArray(Request $request): array
    {
        $logo = $this->logo;
        $publicImage = $logo?->disk === 'public' && $logo->kind === 'image' && str_starts_with($logo->mime_type, 'image/');

        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description,
            'logo_url' => $publicImage ? Storage::disk('public')->url($logo->path) : null,
            'logo_alt' => $publicImage ? ($logo->alt ?: $this->name) : $this->name,
        ];
    }
}
