<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_import_id', 'row_number', 'name', 'messages', 'values'])]
class ProductImportError extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['row_number' => 'integer', 'messages' => 'array', 'values' => 'array'];
    }
}
