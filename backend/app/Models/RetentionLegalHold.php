<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['scope', 'record_id', 'case_reference', 'legal_basis', 'owner_reference', 'started_at', 'review_at', 'released_at'])]
class RetentionLegalHold extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'review_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
