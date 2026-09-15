<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;

/** @extends ApiResource<Cart> */
class CartResource extends ApiResource
{
    public function __construct(Cart $resource, private readonly string $identifier)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
