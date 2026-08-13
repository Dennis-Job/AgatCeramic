<?php

namespace Tests\Feature\Api;

use App\Enums\AdminUserStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_list_update_and_delete_an_employee(): void
    {
        $actor = $this->superAdmin();
        $role = Role::query()->where('slug', 'analyst')->sole();

        $created = $this->actingAs($actor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Analyst User',
                'email' => 'analyst@example.test',
                'password' => 'long-enough-password',
                'password_confirmation' => 'long-enough-password',
                'status' => 'active',
                'role_ids' => [$role->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'analyst@example.test')
            ->assertJsonPath('data.roles.0.slug', 'analyst');

        $employeeId = $created->json('data.id');

        $this->actingAs($actor)
            ->getJson('/api/v1/admin/users?search=analyst')
            ->assertOk()
            ->assertJsonPath('data.0.id', $employeeId);

        $this->actingAs($actor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson("/api/v1/admin/users/{$employeeId}", ['status' => 'blocked'])
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked');

        $this->actingAs($actor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->deleteJson("/api/v1/admin/users/{$employeeId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $employeeId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin-user.deleted', 'entity_id' => $employeeId]);
    }

    public function test_administrator_can_view_but_cannot_manage_employees(): void
    {
        $actor = $this->userWithRole('administrator');
        $employee = User::factory()->create();

        $this->actingAs($actor)->getJson('/api/v1/admin/users')->assertOk();
        $this->actingAs($actor)->patchJson("/api/v1/admin/users/{$employee->id}", ['status' => 'blocked'])->assertForbidden();
    }

    public function test_last_active_super_admin_cannot_be_blocked_or_deleted(): void
    {
        $actor = $this->superAdmin();

        $this->actingAs($actor)->patchJson("/api/v1/admin/users/{$actor->id}", ['status' => AdminUserStatus::Blocked->value])->assertUnprocessable();
        $this->actingAs($actor)->deleteJson("/api/v1/admin/users/{$actor->id}")->assertUnprocessable();
    }

    public function test_changing_an_employee_password_revokes_their_sessions(): void
    {
        $actor = $this->superAdmin();
        $employee = User::factory()->create();
        $this->app['db']->table('sessions')->insert([
            'id' => 'employee-session',
            'user_id' => $employee->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($actor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson("/api/v1/admin/users/{$employee->id}", [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'employee-session']);
    }

    public function test_administrator_is_logged_out_after_changing_their_own_password(): void
    {
        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->patchJson("/api/v1/admin/users/{$actor->id}", [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();

        $this->assertGuest('web');
    }

    private function superAdmin(): User
    {
        return $this->userWithRole('super-admin');
    }

    private function userWithRole(string $slug): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
