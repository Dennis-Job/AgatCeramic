<?php

namespace App\Services;

use App\Models\ContactRequest;
use Illuminate\Support\Facades\DB;

class CallbackRequestService
{
    public function __construct(private readonly CallbackMailService $mail) {}

    /** @param array{name?: string|null, phone: string} $attributes */
    public function create(array $attributes): ContactRequest
    {
        return DB::transaction(function () use ($attributes): ContactRequest {
            $contact = ContactRequest::query()->create([
                'type' => 'callback',
                'name' => $attributes['name'] ?? null,
                'phone' => $attributes['phone'],
                'source' => 'website',
            ]);
            $this->mail->schedule($contact);

            return $contact;
        });
    }
}
