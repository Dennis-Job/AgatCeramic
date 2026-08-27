<?php

namespace App\Models;

use Database\Factories\ProductGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code'])]
class ProductGroup extends Model
{
    /** @use HasFactory<ProductGroupFactory> */
    use HasFactory;

    public function axes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_group_axes')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProductGroupMember::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_group_members')->withTimestamps();
    }
}
