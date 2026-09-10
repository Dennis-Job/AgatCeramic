<?php

namespace Database\Factories;

use App\Models\ContactRequest;
use App\Models\ContactRequestStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactRequestStatusHistory> */
class ContactRequestStatusHistoryFactory extends Factory
{
    protected $model = ContactRequestStatusHistory::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        return [
            'contact_request_id' => ContactRequest::factory(),
            'from_status' => 'new',
            'to_status' => 'processing',
            'actor_id' => User::factory(),
            'actor_snapshot' => ['name' => fake()->name()],
            'occurred_at' => now(),
        ];
    }
}
