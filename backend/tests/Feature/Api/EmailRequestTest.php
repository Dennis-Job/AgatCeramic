<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_an_email_request_without_receiving_contact_data(): void
    {
        $this->postJson('/api/v1/email-requests', [
            'name' => '  Иван Петров  ',
            'email' => '  ivan@example.test  ',
            'message' => '  Нужна консультация по подбору плитки.  ',
        ])
            ->assertCreated()
            ->assertContent('');

        $this->assertDatabaseHas('contact_requests', [
            'type' => 'email',
            'name' => 'Иван Петров',
            'phone' => null,
            'email' => 'ivan@example.test',
            'message' => 'Нужна консультация по подбору плитки.',
            'source' => 'website',
        ]);
    }

    public function test_email_request_requires_an_email_and_message_and_rejects_client_controlled_fields(): void
    {
        $this->postJson('/api/v1/email-requests', [
            'email' => 'not-an-email',
            'message' => ' ',
            'website' => 'https://spam.example',
            'type' => 'callback',
            'source' => 'crm',
            'phone' => '+7 (999) 123-45-67',
            'status' => 'completed',
            'assignee_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => [
                'email', 'message', 'website', 'type', 'source', 'phone', 'status', 'assignee_id',
            ]]]);

        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_email_requests_are_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/email-requests', [
                'email' => "ivan{$attempt}@example.test",
                'message' => 'Нужна консультация.',
            ])->assertCreated();
        }

        $this->postJson('/api/v1/email-requests', [
            'email' => 'ivan@example.test',
            'message' => 'Нужна консультация.',
        ])->assertStatus(429);
    }
}
