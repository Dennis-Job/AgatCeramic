<?php

namespace Database\Factories;

use App\Models\ContactRequest;
use App\Models\ContactRequestComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactRequestComment> */
class ContactRequestCommentFactory extends Factory
{
    protected $model = ContactRequestComment::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        return [
            'contact_request_id' => ContactRequest::factory(),
            'author_id' => User::factory(),
            'author_snapshot' => ['name' => fake()->name()],
            'body' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
