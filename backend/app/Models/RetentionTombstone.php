<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'batch_id', 'scope', 'record_id', 'action', 'key_id', 'subject_hmac', 'occurred_at'])]
class RetentionTombstone extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
