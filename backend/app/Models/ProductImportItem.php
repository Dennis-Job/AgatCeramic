<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_import_id', 'product_id', 'row_number', 'name', 'payload', 'attribute_payload', 'status'])]
class ProductImportItem extends Model
{
    /** @return BelongsTo<ProductImport, $this> */
    public function productImport(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'row_number' => 'integer',
            'payload' => 'array',
            'attribute_payload' => 'array',
        ];
    }
}
