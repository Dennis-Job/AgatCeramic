<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'disk', 'path', 'mime_type', 'size', 'alt', 'is_primary', 'sort_order'])]
class ProductImage extends Model
{
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size' => 'integer', 'is_primary' => 'boolean', 'sort_order' => 'integer'];
    }
}
