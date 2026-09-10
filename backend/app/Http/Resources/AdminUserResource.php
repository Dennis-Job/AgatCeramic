<?php

namespace App\Http\Resources;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** @extends ApiResource<User> */
class AdminUserResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->enumValue($this->status),
            'last_login_at' => $this->dateValue($this->last_login_at),
            'roles' => AdminRoleResource::collection($this->whenLoaded('roles')),
            'permissions' => $this->when(
                $currentUser instanceof User && $this->resource instanceof User && $currentUser->is($this->resource) && $this->relationLoaded('roles'),
                fn (): array => $this->roles
                    ->flatMap(fn ($role) => $role->relationLoaded('permissions') ? $role->permissions->pluck('code') : [])
                    ->unique()
                    ->values()
                    ->all(),
            ),
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    private function dateValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : $value;
    }
}
