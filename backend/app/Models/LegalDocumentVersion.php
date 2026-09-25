<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon|null $published_at */
#[Fillable(['type', 'version', 'body', 'published_at', 'created_by'])]
class LegalDocumentVersion extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }
}
