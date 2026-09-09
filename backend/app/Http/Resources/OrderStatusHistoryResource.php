<?php

namespace App\Http\Resources;

use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;

/** @extends ApiResource<OrderStatusHistory> */
class OrderStatusHistoryResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $actor = $this->actor_snapshot ?? [];

        return [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'actor' => isset($actor['name']) ? [
                'id' => $this->actor_id,
                'name' => $actor['name'],
            ] : null,
            'occurred_at' => $this->occurred_at?->toAtomString(),
        ];
    }
}
