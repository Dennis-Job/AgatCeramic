<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_image_import_id', 'sku', 'entry', 'messages'])]
/** @property list<string>|null $messages */
class ProductImageImportError extends Model
{
    /** @return BelongsTo<ProductImageImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ProductImageImport::class, 'product_image_import_id');
    }

    /** @return array{messages: 'array'} */
    #[\Override]
    protected function casts(): array
    {
        return ['messages' => 'array'];
    }
}
