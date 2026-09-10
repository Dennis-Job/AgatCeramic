<?php

namespace App\Http\Resources;

use App\Models\ContactRequestStatusHistory;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** @extends ApiResource<ContactRequestStatusHistory> */
class ContactRequestStatusHistoryResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $actor = $this->getAttribute('actor_snapshot');

        return [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'actor' => is_array($actor) && isset($actor['name']) && is_string($actor['name']) ? ['id' => $this->actor_id, 'name' => $actor['name']] : null,
            'occurred_at' => $this->dateValue($this->occurred_at),
        ];
    }

    private function dateValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toAtomString() : $value;
    }
}
