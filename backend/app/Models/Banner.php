<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'description', 'image_url', 'link_label', 'link_url', 'is_published'])]
class Banner extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
