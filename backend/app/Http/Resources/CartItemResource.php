<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** @extends ApiResource<CartItem> */
class CartItemResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $primaryImage = $product->primaryImage;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $product->price,
            'line_total' => $product->price === null ? null : $this->lineTotal($product->price, $this->quantity),
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

    private function lineTotal(string $price, int $quantity): string
    {
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '00');
        $minorUnits = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        $total = $minorUnits * $quantity;

        return sprintf('%d.%02d', intdiv($total, 100), $total % 100);
    }
}
