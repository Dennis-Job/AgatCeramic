<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;

/** @extends ApiResource<OrderItem> */
class OrderItemResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total,
        ];
    }
}
