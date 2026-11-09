<?php

namespace Tests\Feature\Api;

use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_order_and_contact_statuses_after_normal_spa_traffic(): void
    {
        $this->freezeTime();
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::query()->where('slug', 'order-manager')->sole());
        $order = Order::factory()->create();
        $contact = ContactRequest::factory()->create();
        $this->actingAs($manager);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/admin/auth/me')->assertOk();
        }

        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()->assertJsonPath('data.status', 'processing');
        $this->patchJson("/api/v1/admin/contact-requests/{$contact->id}/status", ['status' => 'processing'])
            ->assertOk()->assertJsonPath('data.status', 'processing');
        $this->getJson('/api/v1/admin/orders?status=new&per_page=5')->assertOk();
        $this->getJson('/api/v1/admin/contact-requests?status=new&per_page=5')->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
        $this->assertDatabaseHas('contact_requests', ['id' => $contact->id, 'status' => 'processing']);
    }

    public function test_admin_budget_is_per_user_remains_bounded_and_recovers_after_the_window(): void
    {
        $this->freezeTime();
        [$first, $second] = User::factory()->count(2)->create()->all();
        $this->actingAs($first);

        for ($i = 0; $i < 300; $i++) {
            $this->getJson('/api/v1/admin/auth/me')->assertOk();
        }

        $this->getJson('/api/v1/admin/auth/me')->assertStatus(429)
            ->assertHeader('Retry-After', '60')
            ->assertHeader('X-RateLimit-Limit', '300')
            ->assertJsonPath('error.code', 'too_many_requests');
        $this->getJson('/api/v1')->assertOk();
        $this->actingAs($second)->getJson('/api/v1/admin/auth/me')->assertOk();
        $this->actingAs($first)->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])
            ->getJson('/api/v1/admin/auth/me')->assertStatus(429);
        $this->travel(61)->seconds();
        $this->getJson('/api/v1/admin/auth/me')->assertOk();
    }

    public function test_exhausted_public_budget_does_not_block_admin_and_stays_ip_based_when_signed_in(): void
    {
        $this->freezeTime();

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1')->assertOk();
        }

        $this->getJson('/api/v1')->assertStatus(429);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/auth/me')->assertOk();
        $this->getJson('/api/v1')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->getJson('/api/v1')->assertOk();
    }

    public function test_rate_limit_response_preserves_retry_headers_and_the_error_envelope(): void
    {
        $this->freezeTime();

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1')->assertOk();
        }

        $this->getJson('/api/v1')->assertStatus(429)
            ->assertHeader('Retry-After', '60')
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('X-RateLimit-Reset', (string) (now()->getTimestamp() + 60))
            ->assertJsonPath('error.code', 'too_many_requests');
    }
}
