<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CartResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'identifier' => $this->token,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
