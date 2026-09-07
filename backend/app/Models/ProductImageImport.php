<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'original_filename', 'disk', 'path', 'status', 'attempts', 'total_folders', 'processed_folders', 'created_images', 'replaced_images', 'failed_folders', 'error_message', 'started_at', 'completed_at'])]
class ProductImageImport extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ProductImageImportError::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'total_folders' => 'integer', 'processed_folders' => 'integer', 'created_images' => 'integer', 'replaced_images' => 'integer', 'failed_folders' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
