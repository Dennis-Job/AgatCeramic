<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'body', 'is_published'])]
class Page extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
