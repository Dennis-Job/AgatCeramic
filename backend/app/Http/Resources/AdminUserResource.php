<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminUserResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status->value,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'roles' => AdminRoleResource::collection($this->whenLoaded('roles')),
            'permissions' => $this->when(
                $request->user()?->is($this->resource) && $this->relationLoaded('roles'),
                fn (): array => $this->roles
                    ->flatMap(fn ($role) => $role->relationLoaded('permissions') ? $role->permissions->pluck('code') : [])
                    ->unique()
                    ->values()
                    ->all(),
            ),
        ];
    }
}
