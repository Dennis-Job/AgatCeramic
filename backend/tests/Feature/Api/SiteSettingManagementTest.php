<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_email_settings_are_separate_and_notification_address_is_private(): void
    {
        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->patchJson('/api/v1/admin/site-settings', [
            'email' => 'customers@example.test',
            'partner_email' => 'partners@example.test',
            'notification_email' => 'notifications@example.test',
        ])->assertOk()
            ->assertJsonPath('data.email', 'customers@example.test')
            ->assertJsonPath('data.partner_email', 'partners@example.test')
            ->assertJsonPath('data.notification_email', 'notifications@example.test');
        $this->getJson('/api/v1/site-settings')->assertOk()
            ->assertJsonPath('data.email', 'customers@example.test')
            ->assertJsonPath('data.partner_email', 'partners@example.test')
            ->assertJsonMissingPath('data.notification_email');
        $this->actingAs($admin)->patchJson('/api/v1/admin/site-settings', ['seller_name' => 'Продавец'])
            ->assertOk()->assertJsonPath('data.notification_email', 'notifications@example.test');
        $this->actingAs($this->userWithRole('analyst'))->patchJson('/api/v1/admin/site-settings', [
            'notification_email' => 'other@example.test',
        ])->assertForbidden();
    }

    public function test_each_email_is_validated_and_can_be_cleared_independently(): void
    {
        $admin = $this->userWithRole('administrator');
        foreach (['email', 'partner_email', 'notification_email'] as $field) {
            $this->actingAs($admin)->patchJson('/api/v1/admin/site-settings', [$field => 'invalid'])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => [$field]]]);
            $this->actingAs($admin)->patchJson('/api/v1/admin/site-settings', [$field => ' contact@example.test '])
                ->assertOk()->assertJsonPath('data.'.$field, 'contact@example.test');
            $this->actingAs($admin)->patchJson('/api/v1/admin/site-settings', [$field => ''])
                ->assertOk()->assertJsonPath('data.'.$field, null);
        }
    }

    public function test_email_migration_preserves_customer_address_and_initializes_notification_destination(): void
    {
        $migration = require database_path('migrations/2026_10_21_000002_add_email_destinations_to_site_settings.php');
        $migration->down();
        SiteSetting::query()->whereKey(1)->update(['email' => 'existing@example.test']);
        $migration->up();
        $this->assertDatabaseHas('site_settings', [
            'id' => 1, 'email' => 'existing@example.test',
            'notification_email' => 'existing@example.test', 'partner_email' => null,
        ]);
    }

    public function test_public_projection_excludes_bank_and_compliance_data_by_default(): void
    {
        $admin = $this->userWithRole('administrator');
        $this->actingAs($admin)->patchJson('/api/v1/admin/site-settings', [
            'operator_type' => 'individual_entrepreneur', 'seller_name' => 'ИП Тест',
            'phones' => ['+79990000000'], 'email' => 'seller@example.test',
            'bank_name' => 'Банк', 'bank_bik' => '044525225',
            'bank_account' => '12345678901234567890', 'bank_correspondent_account' => '09876543210987654321',
        ])->assertOk();

        $public = $this->getJson('/api/v1/site-settings')->assertOk()
            ->assertJsonPath('data.seller_name', 'ИП Тест')
            ->assertJsonPath('data.bank_details', null);
        $public->assertJsonMissingPath('data.bank_name')->assertJsonMissingPath('data.publish_bank_details');
        $this->actingAs($admin)->patchJson('/api/v1/admin/site-settings', ['publish_bank_details' => true])->assertOk();
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.bank_details.bik', '044525225');
        $this->assertDatabaseHas('audit_logs', ['action' => 'site-settings.updated']);
    }

    public function test_bank_publication_requires_complete_details_and_permissions(): void
    {
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/site-settings')->assertForbidden();
        $this->actingAs($this->userWithRole('analyst'))->patchJson('/api/v1/admin/site-settings', ['seller_name' => 'X'])->assertForbidden();
        $this->actingAs($this->userWithRole('administrator'))->patchJson('/api/v1/admin/site-settings', ['publish_bank_details' => true])
            ->assertUnprocessable();
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.bank_details', null);
    }

    public function test_documents_are_versioned_and_only_published_versions_are_public(): void
    {
        $admin = $this->userWithRole('administrator');
        $created = $this->actingAs($admin)->postJson('/api/v1/admin/legal-documents', [
            'type' => 'privacy_policy', 'version' => 'v1', 'body' => 'Текст политики',
        ])->assertCreated()->assertJsonPath('data.published_at', null);
        $id = $created->json('data.id');
        $this->getJson('/api/v1/legal-documents/privacy_policy')->assertNotFound();
        $this->actingAs($admin)->postJson("/api/v1/admin/legal-documents/{$id}/publish")->assertOk();
        $this->getJson('/api/v1/legal-documents/privacy_policy')->assertOk()->assertJsonPath('data.version', 'v1');
        $this->actingAs($admin)->postJson('/api/v1/admin/legal-documents', [
            'type' => 'privacy_policy', 'version' => 'v1', 'body' => 'Другая версия',
        ])->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/v1/admin/legal-documents', [
            'type' => 'privacy_policy', 'version' => 'v2', 'body' => 'Новый черновик',
        ])->assertCreated();
        $this->getJson('/api/v1/legal-documents/privacy_policy')->assertJsonPath('data.version', 'v1');
    }

    public function test_compliance_approval_is_restricted_and_references_published_policy(): void
    {
        $admin = $this->userWithRole('administrator');
        $superAdmin = $this->userWithRole('super-admin');
        $document = $this->actingAs($admin)->postJson('/api/v1/admin/legal-documents', [
            'type' => 'privacy_policy', 'version' => 'v1', 'body' => 'Политика',
        ])->assertCreated()->json('data.id');
        $payload = [
            'role' => 'legal_reviewer', 'reviewer_name' => 'Тестовый юрист',
            'decision' => 'approved', 'decided_on' => now()->toDateString(), 'document_version_id' => $document,
        ];
        $this->actingAs($admin)->postJson('/api/v1/admin/compliance-approvals', $payload)->assertForbidden();
        $this->actingAs($superAdmin)->postJson('/api/v1/admin/compliance-approvals', $payload)->assertUnprocessable();
        $this->actingAs($admin)->postJson("/api/v1/admin/legal-documents/{$document}/publish")->assertOk();
        $this->actingAs($superAdmin)->postJson('/api/v1/admin/compliance-approvals', $payload)
            ->assertCreated()->assertJsonPath('data.role', 'legal_reviewer');
        $this->actingAs($admin)->getJson('/api/v1/admin/compliance-approvals')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'compliance-approval.recorded']);
        $this->getJson('/api/v1/site-settings')->assertJsonMissingPath('data.compliance_approvals');
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
