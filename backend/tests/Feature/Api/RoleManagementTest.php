<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_a_custom_role(): void
    {
        $actor = $this->superAdmin();
        $permission = Permission::query()->where('code', 'analytics.view')->sole();

        $created = $this->actingAs($actor)->postJson('/api/v1/admin/roles', [
            'name' => 'Reporting editor', 'slug' => 'reporting-editor', 'description' => 'Custom role.',
            'permission_ids' => [$permission->id],
        ])->assertCreated()->assertJsonPath('data.slug', 'reporting-editor');

        $roleId = $created->json('data.id');
        $this->actingAs($actor)->patchJson("/api/v1/admin/roles/{$roleId}", [
            'permission_ids' => [],
        ])->assertOk()->assertJsonCount(0, 'data.permissions');

        $this->assertDatabaseHas('audit_logs', ['action' => 'role.updated', 'entity_id' => $roleId]);
    }

    public function test_system_role_cannot_be_deleted_or_renamed(): void
    {
        $actor = $this->superAdmin();
        $role = Role::query()->where('slug', 'super-admin')->sole();

        $this->actingAs($actor)->deleteJson("/api/v1/admin/roles/{$role->id}")->assertUnprocessable();
        $this->actingAs($actor)->patchJson("/api/v1/admin/roles/{$role->id}", ['name' => 'Other'])->assertUnprocessable();
    }

    public function test_administrator_can_view_but_not_manage_roles(): void
    {
        $actor = $this->userWithRole('administrator');

        $this->actingAs($actor)->getJson('/api/v1/admin/roles')->assertOk();
        $this->actingAs($actor)->postJson('/api/v1/admin/roles', [
            'name' => 'Blocked role', 'slug' => 'blocked-role', 'permission_ids' => [],
        ])->assertForbidden();
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
