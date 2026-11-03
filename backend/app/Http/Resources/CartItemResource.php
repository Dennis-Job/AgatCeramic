<?php

namespace App\Http\Resources;

use App\Data\PricedCartItem;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** @extends ApiResource<CartItem> */
class CartItemResource extends ApiResource
{
    public function __construct(private readonly PricedCartItem $pricedItem)
    {
        parent::__construct($pricedItem->item);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $product = $this->product;
        if ($product === null) {
            return [
                'id' => $this->id,
                'product_id' => $this->product_id,
                'quantity' => $this->quantity,
                'unit_price' => null,
                'line_total' => null,
                'product' => null,
                'created_at' => $this->created_at?->toAtomString(),
                'updated_at' => $this->updated_at?->toAtomString(),
            ];
        }
        $primaryImage = $product->primaryImage;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->pricedItem->unitPrice,
            'line_total' => $this->pricedItem->lineTotal,
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'primary_image_url' => $primaryImage === null ? null : Storage::disk($primaryImage->disk)->url($primaryImage->path),
            ],
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
