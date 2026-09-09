<?php

namespace App\Services;

use App\Models\ContactRequest;

class PartnerRequestService
{
    /** @param array{name: string, phone: string, email: string, message: string} $attributes */
    public function create(array $attributes): ContactRequest
    {
        return ContactRequest::query()->create([
            'type' => 'partner',
            'name' => $attributes['name'],
            'phone' => $attributes['phone'],
            'email' => $attributes['email'],
            'message' => $attributes['message'],
            'source' => 'website',
        ]);
    }
}
