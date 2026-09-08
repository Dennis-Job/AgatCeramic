<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class OrderStatusResource extends ApiResource
{
    /** @return array<string, mixed> */
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
