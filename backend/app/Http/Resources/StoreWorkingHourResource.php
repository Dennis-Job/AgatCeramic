<?php

namespace App\Http\Resources;

use App\Models\StoreWorkingHour;
use Illuminate\Http\Request;

/** @extends ApiResource<StoreWorkingHour> */
class StoreWorkingHourResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'weekday' => $this->weekday,
            'is_closed' => $this->is_closed,
            'opens_at' => $this->is_closed ? null : substr((string) $this->opens_at, 0, 5),
            'closes_at' => $this->is_closed ? null : substr((string) $this->closes_at, 0, 5),
        ];
    }
}
