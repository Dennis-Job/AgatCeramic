<?php

namespace App\Models;

use Database\Factories\ProductCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['brand_id', 'name', 'slug', 'headline', 'description', 'image_id', 'is_active'])]
class ProductCollection extends Model
{
    /** @use HasFactory<ProductCollectionFactory> */
    use HasFactory;

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'collection_id');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
