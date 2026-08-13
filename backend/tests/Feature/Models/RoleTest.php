<?php

namespace Tests\Feature\Models;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_have_multiple_roles(): void
    {
        $user = User::factory()->create();
        $roles = Role::factory()->count(2)->create();

        $user->roles()->attach($roles);

        $this->assertSame($roles->pluck('id')->sort()->values()->all(), $user->roles()->pluck('roles.id')->sort()->values()->all());
        $this->assertSame([$user->id], $roles->first()->users()->pluck('users.id')->all());
    }

    public function test_baseline_roles_are_seeded_idempotently(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(7, Role::query()->count());
        $this->assertDatabaseHas('roles', ['slug' => 'super-admin', 'name' => 'Супер Администратор']);
        $this->assertDatabaseHas('roles', ['slug' => 'analyst', 'name' => 'Аналитик']);
    }
}
