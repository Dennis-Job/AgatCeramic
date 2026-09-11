<?php

namespace App\Http\Resources;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** @extends ApiResource<Order> */
class AdminOrderResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer' => ['name' => $this->customer_name, 'phone' => $this->customer_phone, 'email' => $this->customer_email],
            'delivery_address' => $this->delivery_address,
            'customer_comment' => $this->customer_comment,
            'status' => $this->status,
            'payment_status' => $this->enumValue($this->payment_status),
            'payment_amount' => $this->payment_amount,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'total_amount' => $this->total_amount,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'paid_at' => $this->dateValue($this->paid_at),
            'completed_at' => $this->dateValue($this->completed_at),
            'created_at' => $this->dateValue($this->created_at),
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    private function dateValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toAtomString() : $value;
    }
}
