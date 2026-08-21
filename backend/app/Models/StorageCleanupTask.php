<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['disk', 'path', 'status', 'attempts', 'last_error', 'last_attempted_at', 'next_attempt_at', 'dispatched_at', 'completed_at'])]
class StorageCleanupTask extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempted_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
