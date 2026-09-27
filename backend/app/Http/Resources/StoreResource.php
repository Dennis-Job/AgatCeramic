<?php

namespace App\Http\Resources;

use App\Models\Store;
use Illuminate\Http\Request;

/** @extends ApiResource<Store> */
class StoreResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'is_published' => $this->is_published,
            'working_hours' => StoreWorkingHourResource::collection($this->whenLoaded('workingHours')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
