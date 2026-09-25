<?php

namespace App\Http\Resources;

use App\Models\ComplianceApproval;
use Illuminate\Http\Request;

/** @extends ApiResource<ComplianceApproval> */
class ComplianceApprovalResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'reviewer_name' => $this->reviewer_name,
            'decision' => $this->decision,
            'decided_on' => $this->decided_on->format('Y-m-d'),
            'document_version_id' => $this->document_version_id,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
