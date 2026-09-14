<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['batch_id', 'policy_version', 'scope', 'action', 'mode', 'status', 'cutoff_at', 'eligible_count', 'processed_count', 'hold_count', 'exception_count', 'error_count', 'failure_code', 'service_identity', 'occurred_at'])]
class RetentionExecution extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'batch_id';

    protected $keyType = 'string';

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'cutoff_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }
}
