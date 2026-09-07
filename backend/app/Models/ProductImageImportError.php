<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_image_import_id', 'sku', 'entry', 'messages'])]
class ProductImageImportError extends Model
{
    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImageImport::class, 'product_image_import_id');
    }

    protected function casts(): array
    {
        return ['messages' => 'array'];
    }
}
