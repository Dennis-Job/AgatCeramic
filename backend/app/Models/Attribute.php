<?php

namespace App\Models;

use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['attribute_group_id', 'name', 'slug', 'type', 'unit', 'is_filterable', 'is_required', 'sort_order'])]
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    public const TYPES = ['text', 'number', 'boolean', 'select', 'multiselect'];

    /** @return BelongsTo<AttributeGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class, 'attribute_group_id');
    }

    /** @return HasMany<AttributeOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order')->orderBy('label');
    }

    public function acceptsOptions(): bool
    {
        return in_array($this->type, ['select', 'multiselect'], true);
    }
}
