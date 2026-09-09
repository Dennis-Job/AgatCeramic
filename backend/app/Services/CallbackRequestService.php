<?php

namespace App\Services;

use App\Models\ContactRequest;

class CallbackRequestService
{
    /** @param array{name?: string|null, phone: string} $attributes */
    public function create(array $attributes): ContactRequest
    {
        return ContactRequest::query()->create([
            'type' => 'callback',
            'name' => $attributes['name'] ?? null,
            'phone' => $attributes['phone'],
            'source' => 'website',
        ]);
    }
}
