<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['eyebrow', 'title', 'description', 'image_url', 'image_media_id', 'image_alt', 'link_label', 'link_url', 'is_published'])]
class Banner extends Model
{
    /** @return BelongsTo<Media, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
