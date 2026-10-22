<?php

namespace App\Services;

use App\Models\ContactRequest;
use Illuminate\Support\Facades\DB;

class PartnerRequestService
{
    public function __construct(private readonly CallbackMailService $mail) {}

    /** @param array{name: string, phone: string, email: string, message: string} $attributes */
    public function create(array $attributes): ContactRequest
    {
        return DB::transaction(function () use ($attributes): ContactRequest {
            $contact = ContactRequest::query()->create([
                'type' => 'partner',
                'name' => $attributes['name'],
                'phone' => $attributes['phone'],
                'email' => $attributes['email'],
                'message' => $attributes['message'],
                'source' => 'website',
            ]);
            $this->mail->schedule($contact);

            return $contact;
        });
    }
}
