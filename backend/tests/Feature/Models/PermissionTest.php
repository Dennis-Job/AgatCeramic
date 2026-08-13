<?php

namespace Tests\Feature\Models;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_inherits_permissions_from_assigned_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['code' => 'orders.view']);

        $user->roles()->attach($role);
        $role->permissions()->attach($permission);

        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertFalse($user->hasPermission('orders.manage'));
    }

    public function test_baseline_permission_matrix_is_seeded_idempotently(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertSame(19, Permission::query()->count());
        $this->assertSame(19, Role::query()->where('slug', 'super-admin')->firstOrFail()->permissions()->count());
        $this->assertSame(['analytics.view'], Role::query()->where('slug', 'analyst')->firstOrFail()->permissions()->pluck('code')->all());
    }
}
