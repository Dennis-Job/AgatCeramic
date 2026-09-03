<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'original_filename', 'disk', 'path', 'status', 'attempts', 'created_rows',
    'updated_rows', 'processed_rows', 'error_message', 'started_at', 'completed_at',
    'category_id', 'total_rows', 'failed_rows', 'last_processed_row',
])]
class ProductImport extends Model
{
    public function rowErrors(): HasMany
    {
        return $this->hasMany(ProductImportError::class)->orderBy('row_number');
    }

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
            'category_id' => 'integer',
            'total_rows' => 'integer',
            'failed_rows' => 'integer',
            'last_processed_row' => 'integer',
            'created_rows' => 'integer',
            'updated_rows' => 'integer',
            'processed_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
