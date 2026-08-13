<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrativePoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_cannot_manage_users_roles_or_permissions(): void
    {
        $user = $this->userWithRole('analyst');

        $this->assertFalse($user->can('viewAny', User::class));
        $this->assertFalse($user->can('create', Role::class));
        $this->assertFalse($user->can('update', Permission::factory()->create()));
    }

    public function test_administrator_can_view_but_not_manage_access_control_entities(): void
    {
        $user = $this->userWithRole('administrator');

        $this->assertTrue($user->can('viewAny', User::class));
        $this->assertTrue($user->can('viewAny', Role::class));
        $this->assertTrue($user->can('viewAny', Permission::class));
        $this->assertFalse($user->can('update', Role::factory()->create()));
        $this->assertFalse($user->can('update', Permission::factory()->create()));
    }

    public function test_super_admin_can_manage_access_control_entities(): void
    {
        $user = $this->userWithRole('super-admin');

        $this->assertTrue($user->can('create', User::class));
        $this->assertTrue($user->can('update', User::factory()->create()));
        $this->assertTrue($user->can('delete', Role::factory()->create()));
        $this->assertTrue($user->can('update', Permission::factory()->create()));
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
