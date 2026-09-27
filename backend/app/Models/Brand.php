<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'description', 'country_code', 'logo_id', 'is_active'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    /** @return BelongsTo<Media, $this> */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_id');
    }

    /** @return BelongsToMany<Media, $this> */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'brand_media_documents')->withPivot('sort_order')
            ->orderByPivot('sort_order')->orderBy('media.id');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
