<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_a_partner_request_without_receiving_contact_data(): void
    {
        $this->postJson('/api/v1/partner-requests', [
            'name' => '  Иван Петров  ',
            'phone' => '  +7 (999) 123-45-67  ',
            'email' => '  ivan@example.test  ',
            'message' => '  Предлагаем сотрудничество.  ',
        ])
            ->assertCreated()
            ->assertContent('');

        $this->assertDatabaseHas('contact_requests', [
            'type' => 'partner',
            'name' => 'Иван Петров',
            'phone' => '+7 (999) 123-45-67',
            'email' => 'ivan@example.test',
            'message' => 'Предлагаем сотрудничество.',
            'source' => 'website',
        ]);
    }

    public function test_partner_request_requires_full_contact_data_and_rejects_client_controlled_fields(): void
    {
        $this->postJson('/api/v1/partner-requests', [
            'name' => ' ',
            'phone' => '123',
            'email' => 'not-an-email',
            'message' => ' ',
            'website' => 'https://spam.example',
            'type' => 'email',
            'source' => 'crm',
            'status' => 'completed',
            'assignee_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => [
                'name', 'phone', 'email', 'message', 'website', 'type', 'source', 'status', 'assignee_id',
            ]]]);

        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_partner_requests_are_rate_limited_per_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/partner-requests', $this->payload("ivan{$attempt}@example.test"))
                ->assertCreated();
        }

        $this->postJson('/api/v1/partner-requests', $this->payload('ivan@example.test'))
            ->assertStatus(429);
    }

    /** @return array<string, string> */
    private function payload(string $email): array
    {
        return [
            'name' => 'Иван Петров',
            'phone' => '+7 (999) 123-45-67',
            'email' => $email,
            'message' => 'Предлагаем сотрудничество.',
        ];
    }
}
