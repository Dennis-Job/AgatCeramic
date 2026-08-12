<?php

namespace Tests\Unit\Models;

use App\Enums\AdminUserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_uses_the_active_status_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AdminUserStatus::Active, $user->status);
        $this->assertNull($user->last_login_at);
    }

    public function test_admin_user_can_be_blocked(): void
    {
        $user = User::factory()->blocked()->create();

        $this->assertSame(AdminUserStatus::Blocked, $user->status);
    }
}
