<?php

namespace Database\Factories;

use App\Models\ContactRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactRequest> */
class ContactRequestFactory extends Factory
{
    protected $model = ContactRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => 'callback',
            'name' => fake()->name(),
            'phone' => '+7 (999) 123-45-67',
            'email' => null,
            'message' => null,
            'source' => 'website',
        ];
    }
}
