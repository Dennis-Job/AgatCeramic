<?php

namespace App\Http\Resources;

use App\Models\LegalDocumentVersion;
use Illuminate\Http\Request;

/** @extends ApiResource<LegalDocumentVersion> */
class LegalDocumentVersionResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'version' => $this->version,
            'body' => $this->body,
            'published_at' => $this->published_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
