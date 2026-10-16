<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AdminAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchanged_profile_does_not_create_an_audit_event(): void
    {
        $user = User::factory()->create();
        $this->app->make(AdminAuthenticationService::class)->updateProfile($user, ['name' => $user->name, 'email' => $user->email]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_profile_audit_lists_only_changed_personal_fields_without_values(): void
    {
        $user = User::factory()->create(['name' => 'Previous private profile']);
        $this->app->make(AdminAuthenticationService::class)->updateProfile($user, ['name' => 'Replacement private profile', 'email' => $user->email]);

        $metadata = AuditLog::query()->where('action', 'auth.profile.updated')->sole()->metadata;
        $this->assertSame(['name'], $metadata['changed_fields']);
        $this->assertSame(['name'], $metadata['fields']);
        $this->assertFalse($metadata['credentials_changed']);
        $this->assertSame([], $metadata['changes'] ?? []);
        $serialized = json_encode($metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Previous private profile', $serialized);
        $this->assertStringNotContainsString('Replacement private profile', $serialized);
        $this->assertStringNotContainsString($user->email, $serialized);
    }

    public function test_password_profile_audit_has_only_a_field_name_and_preserves_legacy_metadata(): void
    {
        $user = User::factory()->create();
        $updated = $this->app->make(AdminAuthenticationService::class)->updateProfile($user, ['password' => 'private-profile-password']);

        $metadata = AuditLog::query()->where('action', 'auth.profile.updated')->sole()->metadata;
        $this->assertSame(['password'], $metadata['fields']);
        $this->assertSame([], $metadata['changed_fields']);
        $this->assertTrue($metadata['credentials_changed']);
        $this->assertSame([], $metadata['changes'] ?? []);
        $serialized = json_encode($metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('private-profile-password', $serialized);
        $this->assertStringNotContainsString($updated->getRawOriginal('password'), $serialized);
        $this->assertStringNotContainsString($updated->getRawOriginal('remember_token'), $serialized);
    }

    public function test_profile_audit_uses_fresh_state_when_the_authenticated_user_is_stale(): void
    {
        $user = User::factory()->create(['name' => 'Old profile state']);
        User::query()->whereKey($user->id)->update(['name' => 'Current profile state']);

        $updated = $this->app->make(AdminAuthenticationService::class)->updateProfile($user, ['name' => 'Current profile state']);

        $this->assertSame('Current profile state', $updated->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
