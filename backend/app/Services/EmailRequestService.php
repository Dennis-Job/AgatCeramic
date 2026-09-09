<?php

namespace App\Services;

use App\Models\ContactRequest;

class EmailRequestService
{
    /** @param array{name?: string|null, email: string, message: string} $attributes */
    public function create(array $attributes): ContactRequest
    {
        return ContactRequest::query()->create([
            'type' => 'email',
            'name' => $attributes['name'] ?? null,
            'email' => $attributes['email'],
            'message' => $attributes['message'],
            'source' => 'website',
        ]);
    }
}
