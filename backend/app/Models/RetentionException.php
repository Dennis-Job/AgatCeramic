<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['scope', 'record_id', 'reason_code', 'first_detected_at', 'last_detected_at', 'resolved_at'])]
class RetentionException extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
