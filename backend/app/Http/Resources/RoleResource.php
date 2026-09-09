<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;

/** @extends ApiResource<Role> */
class RoleResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
