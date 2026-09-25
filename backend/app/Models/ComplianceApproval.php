<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $decided_on */
#[Fillable(['role', 'reviewer_name', 'decision', 'decided_on', 'document_version_id', 'recorded_by'])]
class ComplianceApproval extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['decided_on' => 'immutable_date'];
    }
}
