<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address', 'phone', 'is_published'])]
class Store extends Model
{
    /** @return HasMany<StoreWorkingHour, $this> */
    public function workingHours(): HasMany
    {
        return $this->hasMany(StoreWorkingHour::class)->orderBy('weekday');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
