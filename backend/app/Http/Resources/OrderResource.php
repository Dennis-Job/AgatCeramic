<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;

/** @extends ApiResource<Order> */
class OrderResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status->value,
            'total_amount' => $this->total_amount,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
