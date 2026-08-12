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
        ];
    }
}
