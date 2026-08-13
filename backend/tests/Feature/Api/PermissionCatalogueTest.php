<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_the_permission_catalogue_with_assigned_roles(): void
    {
        $actor = $this->userWithRole('administrator');

        $this->actingAs($actor)->getJson('/api/v1/admin/permissions')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'admin-users.manage')
            ->assertJsonPath('data.0.roles.0.slug', 'super-admin');
    }

    public function test_catalogue_can_be_filtered_by_permission_module(): void
    {
        $actor = $this->userWithRole('super-admin');

        $this->actingAs($actor)->getJson('/api/v1/admin/permissions?module=analytics')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'analytics.view');
    }

    public function test_user_without_permission_access_cannot_view_the_catalogue(): void
    {
        $actor = $this->userWithRole('analyst');

        $this->actingAs($actor)->getJson('/api/v1/admin/permissions')->assertForbidden();
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
