<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['import_type', 'import_id', 'status', 'attempts', 'last_error', 'dispatched_at', 'next_attempt_at', 'completed_at'])]
class ImportDispatchTask extends Model
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_PRODUCT_IMAGE = 'product_image';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'import_id' => 'integer',
            'attempts' => 'integer',
            'dispatched_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
