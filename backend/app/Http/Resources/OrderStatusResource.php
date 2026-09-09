<?php

namespace App\Http\Resources;

use App\Models\OrderStatus;
use Illuminate\Http\Request;

/** @extends ApiResource<OrderStatus> */
class OrderStatusResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'is_terminal' => $this->is_terminal,
        ];
    }
}
