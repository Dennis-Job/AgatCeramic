<?php

namespace App\Http\Resources;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** @extends ApiResource<Order> */
class PaymentRegistrationResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'payment_status' => $this->enumValue($this->payment_status),
            'payment_amount' => $this->payment_amount,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'paid_at' => $this->dateValue($this->paid_at),
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
