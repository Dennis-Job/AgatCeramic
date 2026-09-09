<?php

namespace Tests\Feature\Api;

use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallbackRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_a_callback_request_without_receiving_contact_data(): void
    {
        $this->postJson('/api/v1/callback-requests', [
            'name' => '  Иван Петров  ',
            'phone' => '  +7 (999) 123-45-67  ',
        ])
            ->assertCreated()
            ->assertContent('');

        $this->assertDatabaseHas('contact_requests', [
            'type' => 'callback',
            'name' => 'Иван Петров',
            'phone' => '+7 (999) 123-45-67',
            'email' => null,
            'message' => null,
            'source' => 'website',
        ]);
        $this->assertDatabaseCount('contact_requests', 1);
    }

    public function test_callback_request_validates_phone_and_rejects_client_controlled_or_spam_fields(): void
    {
        $this->postJson('/api/v1/callback-requests', [
            'phone' => '123',
            'website' => 'https://spam.example',
            'type' => 'partner',
            'source' => 'crm',
            'email' => 'ivan@example.test',
            'message' => 'Позвоните мне',
            'status' => 'completed',
            'assignee_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => [
                'phone', 'website', 'type', 'source', 'email', 'message', 'status', 'assignee_id',
            ]]]);

        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_callback_requests_are_rate_limited_per_ip(): void
    {
        ContactRequest::factory()->count(5)->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/callback-requests', ['phone' => '+7 (999) 123-45-67'])
                ->assertCreated();
        }

        $this->postJson('/api/v1/callback-requests', ['phone' => '+7 (999) 123-45-67'])
            ->assertStatus(429);
    }
}
