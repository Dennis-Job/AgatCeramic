<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_initial_super_admin_with_baseline_access_control_data(): void
    {
        $this->artisan('admin:bootstrap', [
            '--name' => 'Initial Administrator',
            '--email' => 'admin@example.test',
            '--password' => 'correct-horse-battery-staple',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('correct-horse-battery-staple', $user->password));
        $this->assertTrue($user->roles()->where('slug', 'super-admin')->exists());
        $this->assertTrue($user->hasPermission('audit-log.view'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => null,
            'action' => 'admin.bootstrap',
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    public function test_it_refuses_to_create_another_initial_administrator(): void
    {
        User::factory()->create();

        $this->artisan('admin:bootstrap', [
            '--email' => 'another@example.test',
            '--password' => 'correct-horse-battery-staple',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
