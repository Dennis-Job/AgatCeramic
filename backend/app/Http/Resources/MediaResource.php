<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** @extends ApiResource<Media> */
class MediaResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'url' => Storage::disk($this->disk)->url($this->path),
            'thumbnail_url' => $this->thumbnail_path ? Storage::disk($this->disk)->url($this->thumbnail_path) : null,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'title' => $this->title,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
