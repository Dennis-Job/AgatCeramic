<?php

namespace App\Http\Resources;

use App\Models\Permission;
use Illuminate\Http\Request;

/** @extends ApiResource<Permission> */
class PermissionResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'roles' => AdminRoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
