<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;

/** @extends ApiResource<Role> */
class AdminRoleResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
