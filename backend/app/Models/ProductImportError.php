<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_import_id', 'row_number', 'name', 'messages', 'values'])]
/** @property array<string, mixed>|null $values */
class ProductImportError extends Model
{
    public $timestamps = false;

    /** @return array{row_number: 'integer', messages: 'array', values: 'array'} */
    #[\Override]
    protected function casts(): array
    {
        return ['row_number' => 'integer', 'messages' => 'array', 'values' => 'array'];
    }
}
