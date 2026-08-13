<?php

namespace Tests\Feature\Api;

use App\Enums\AdminUserStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_login_and_read_the_current_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'last_login_at' => null,
        ]);

        $this->fromAdminSpa()
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/login', [
                'email' => 'ADMIN@example.test',
                'password' => 'password',
            ])
            ->assertNoContent();

        $this->assertNotNull($user->fresh()->last_login_at);

        $this->fromAdminSpa()
            ->getJson('/api/v1/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.status', AdminUserStatus::Active->value)
            ->assertJsonMissingPath('data.password');
    }

    public function test_login_rejects_invalid_credentials_with_the_standard_api_error(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        $this->fromAdminSpa()
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/login', [
                'email' => 'admin@example.test',
                'password' => 'incorrect-password',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_blocked_admin_cannot_login(): void
    {
        User::factory()->blocked()->create(['email' => 'blocked@example.test']);

        $this->fromAdminSpa()
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/login', [
                'email' => 'blocked@example.test',
                'password' => 'password',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_blocked_admin_session_cannot_access_protected_routes(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->fromAdminSpa()
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertNoContent();

        $user->update(['status' => AdminUserStatus::Blocked]);

        $this->fromAdminSpa()
            ->getJson('/api/v1/admin/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_logout_invalidates_the_administrator_session(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->fromAdminSpa()
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertNoContent();

        $this->fromAdminSpa()
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/logout')
            ->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited(): void
    {
        $email = 'rate-limited-'.Str::uuid().'@example.test';
        User::factory()->create(['email' => $email]);

        foreach (range(1, 5) as $attempt) {
            $this->fromAdminSpa()
                ->withoutMiddleware(ValidateCsrfToken::class)
                ->postJson('/api/v1/admin/auth/login', [
                    'email' => $email,
                    'password' => 'incorrect-password',
                ])
                ->assertUnauthorized();
        }

        $this->fromAdminSpa()
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $email,
                'password' => 'incorrect-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_protected_admin_route_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_current_admin_response_contains_effective_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'analyst')->sole());

        $this->actingAs($user)
            ->getJson('/api/v1/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('data.permissions.0', 'analytics.view');
    }

    private function fromAdminSpa(): static
    {
        return $this->withHeader('Origin', 'http://localhost:5173');
    }
}
