<?php

namespace App\Http\Resources;

use App\Models\ContactRequest;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** @extends ApiResource<ContactRequest> */
class ContactRequestResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'contact' => [
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
            ],
            'message' => $this->message,
            'source' => $this->source,
            'status' => $this->enumValue($this->status),
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'assigned_at' => $this->dateValue($this->assigned_at),
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
