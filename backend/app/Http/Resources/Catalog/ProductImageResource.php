<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'url' => Storage::disk($this->disk)->url($this->path),
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'alt' => $this->alt,
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
