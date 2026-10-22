<?php

namespace App\Services;

use App\Models\ContactRequest;
use Illuminate\Support\Facades\DB;

class EmailRequestService
{
    public function __construct(private readonly CallbackMailService $mail) {}

    /** @param array{name?: string|null, email: string, message: string} $attributes */
    public function create(array $attributes): ContactRequest
    {
        return DB::transaction(function () use ($attributes): ContactRequest {
            $contact = ContactRequest::query()->create([
                'type' => 'email',
                'name' => $attributes['name'] ?? null,
                'email' => $attributes['email'],
                'message' => $attributes['message'],
                'source' => 'website',
            ]);
            $this->mail->schedule($contact);

            return $contact;
        });
    }
}
