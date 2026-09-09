<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ContactRequestResource extends ApiResource
{
    /** @return array<string, mixed> */
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
            'status' => $this->status->value,
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'assigned_at' => $this->assigned_at?->toAtomString(),
            'completed_at' => $this->completed_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
