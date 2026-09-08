<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PaymentRegistrationResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'payment_status' => $this->payment_status->value,
            'payment_amount' => $this->payment_amount,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'paid_at' => $this->paid_at?->toAtomString(),
        ];
    }
}
