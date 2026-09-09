<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;

/** @extends ApiResource<Cart> */
class CartResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
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
