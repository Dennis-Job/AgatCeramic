<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'is_published'])]
class Slider extends Model
{
    /** @return BelongsToMany<Banner, $this> */
    public function banners(): BelongsToMany
    {
        return $this->belongsToMany(Banner::class, 'slider_banner')->withPivot('position')->orderByPivot('position');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
