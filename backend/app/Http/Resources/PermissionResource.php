<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PermissionResource extends ApiResource
{
    /** @return array<string, mixed> */
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
