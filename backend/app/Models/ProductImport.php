<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'original_filename', 'disk', 'path', 'status', 'attempts', 'created_rows',
    'updated_rows', 'processed_rows', 'error_message', 'started_at', 'completed_at',
])]
class ProductImport extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'created_rows' => 'integer',
            'updated_rows' => 'integer',
            'processed_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
