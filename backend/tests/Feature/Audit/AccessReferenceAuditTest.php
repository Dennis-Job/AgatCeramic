<?php

namespace Tests\Feature\Audit;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\AttributeOption;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminUserManagementService;
use App\Services\AttributeGroupManagementService;
use App\Services\AttributeManagementService;
use App\Services\BrandManagementService;
use App\Services\CategoryAttributeManagementService;
use App\Services\CategoryManagementService;
use App\Services\RoleManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AccessReferenceAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_changes_preserve_status_and_role_labels_without_personal_values(): void
    {
        $actor = User::factory()->create();
        $employee = User::factory()->create(['name' => 'Private old employee', 'email' => 'private-old@example.test']);
        $role = Role::factory()->create(['name' => 'Catalog reviewers', 'slug' => 'catalog-reviewers']);

        $this->app->make(AdminUserManagementService::class)->update($actor, $employee, [
            'status' => 'blocked', 'role_ids' => [$role->id],
            'name' => 'Private new employee', 'email' => 'private-new@example.test',
        ]);

        $metadata = AuditLog::query()->where('action', 'admin-user.updated')->sole()->metadata;
        $this->assertSame(['before' => 'active', 'after' => 'blocked'], $metadata['changes']['status']);
        $this->assertSame(['name', 'email'], $metadata['fields']);
        $role->update(['name' => 'Later role label']);
        $metadata = AuditLog::query()->where('action', 'admin-user.updated')->sole()->metadata;
        $serialized = json_encode($metadata, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Catalog reviewers', $serialized);
        $this->assertStringNotContainsString('Private old employee', $serialized);
        $this->assertStringNotContainsString('Private new employee', $serialized);
        $this->assertStringNotContainsString('private-old', $serialized);
        $this->assertStringNotContainsString('private-new', $serialized);
    }

    public function test_employee_no_op_is_silent_and_password_change_records_only_the_field(): void
    {
        $actor = User::factory()->create();
        $employee = User::factory()->create();
        $service = $this->app->make(AdminUserManagementService::class);
        $service->update($actor, $employee, ['name' => $employee->name, 'email' => $employee->email, 'status' => 'active', 'role_ids' => []]);
        $this->assertDatabaseCount('audit_logs', 0);

        $service->update($actor, $employee, ['password' => 'a-new-private-password']);
        $metadata = AuditLog::query()->where('action', 'admin-user.updated')->sole()->metadata;
        $this->assertSame(['password'], $metadata['fields']);
        $this->assertArrayNotHasKey('password', $metadata['changes'] ?? []);
        $this->assertStringNotContainsString('a-new-private-password', json_encode($metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($employee->fresh()->getRawOriginal('password'), json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_role_diff_preserves_permission_labels_and_no_op_is_silent(): void
    {
        $actor = User::factory()->create();
        $actorRole = Role::factory()->create();
        $permission = Permission::factory()->create(['name' => 'Read inventory', 'code' => 'inventory.view']);
        $actorRole->permissions()->attach($permission);
        $actor->roles()->attach($actorRole);
        $role = Role::factory()->create(['name' => 'Old role', 'is_system' => false]);
        $service = $this->app->make(RoleManagementService::class);
        $service->update($actor, $role, ['name' => 'New role', 'permission_ids' => [$permission->id]]);
        $metadata = AuditLog::query()->where('action', 'role.updated')->sole()->metadata;
        $this->assertSame(['before' => 'Old role', 'after' => 'New role'], $metadata['changes']['title']);
        $permission->update(['name' => 'Later permission label']);
        $metadata = AuditLog::query()->where('action', 'role.updated')->sole()->metadata;
        $this->assertStringContainsString('Read inventory', json_encode($metadata, JSON_THROW_ON_ERROR));
        $service->update($actor, $role, ['name' => 'New role', 'permission_ids' => [$permission->id]]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_personal_field_only_changes_are_audited_without_values(): void
    {
        $actor = User::factory()->create();
        $employee = User::factory()->create(['name' => 'Previous private employee']);
        $this->app->make(AdminUserManagementService::class)->update($actor, $employee, ['name' => 'Replacement private employee']);

        $metadata = AuditLog::query()->where('action', 'admin-user.updated')->sole()->metadata;
        $this->assertSame(['name'], $metadata['fields']);
        $this->assertSame([], $metadata['changes'] ?? []);
        $serialized = json_encode($metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Previous private employee', $serialized);
        $this->assertStringNotContainsString('Replacement private employee', $serialized);
    }

    public function test_catalog_reference_updates_include_diffs_and_skip_no_ops(): void
    {
        $actor = User::factory()->create();
        foreach ([
            [Brand::factory()->create(['name' => 'Old brand']), BrandManagementService::class, 'brand.updated'],
            [Category::factory()->create(['name' => 'Old category']), CategoryManagementService::class, 'category.updated'],
            [AttributeGroup::factory()->create(['name' => 'Old group']), AttributeGroupManagementService::class, 'attribute-group.updated'],
        ] as [$entity, $serviceClass, $action]) {
            $oldTitle = $entity->name;
            $service = $this->app->make($serviceClass);
            $service->update($actor, $entity, ['name' => 'Revised title']);
            $metadata = AuditLog::query()->where('action', $action)->sole()->metadata;
            $this->assertSame(['before' => $oldTitle, 'after' => 'Revised title'], $metadata['changes']['title']);
            $service->update($actor, $entity, ['name' => 'Revised title']);
            $this->assertSame(1, AuditLog::query()->where('action', $action)->count());
        }
    }

    public function test_option_label_changes_are_audited_and_identical_replacement_is_silent(): void
    {
        $actor = User::factory()->create();
        $attribute = Attribute::factory()->create(['type' => 'select']);
        AttributeOption::factory()->create(['attribute_id' => $attribute->id, 'value' => 'matte', 'label' => 'Old matte', 'sort_order' => 0]);
        $service = $this->app->make(AttributeManagementService::class);
        $payload = ['options' => [['value' => 'matte', 'label' => 'New matte', 'sort_order' => 0]]];
        $service->update($actor, $attribute, $payload);
        $metadata = AuditLog::query()->where('action', 'attribute.updated')->sole()->metadata;
        $this->assertStringContainsString('Old matte', json_encode($metadata['changes']['options']['before'], JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('New matte', json_encode($metadata['changes']['options']['after'], JSON_THROW_ON_ERROR));
        $this->assertTrue($metadata['options_replaced']);
        $service->update($actor, $attribute, $payload);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_category_assignment_diff_captures_requiredness_and_group_removal_consequences(): void
    {
        $actor = User::factory()->create();
        $category = Category::factory()->create();
        $group = AttributeGroup::factory()->create(['name' => 'Surface details']);
        $attribute = Attribute::factory()->create(['attribute_group_id' => $group->id, 'name' => 'Finish']);
        $category->attributeGroups()->attach($group, ['sort_order' => 0]);
        $category->attributes()->attach($attribute, ['sort_order' => 0, 'is_required' => false]);
        $service = $this->app->make(CategoryAttributeManagementService::class);
        $service->replace($actor, $category, [['id' => $attribute->id, 'sort_order' => 0, 'is_required' => true]]);
        $metadata = AuditLog::query()->where('action', 'category.attributes-updated')->sole()->metadata;
        $this->assertNotSame($metadata['changes']['attributes']['before'], $metadata['changes']['attributes']['after']);
        $service->replace($actor, $category, [['id' => $attribute->id, 'sort_order' => 0, 'is_required' => true]]);
        $this->assertSame(1, AuditLog::query()->where('action', 'category.attributes-updated')->count());

        $service->replaceGroups($actor, $category, []);
        $metadata = AuditLog::query()->where('action', 'category.attribute-groups-updated')->sole()->metadata;
        $this->assertNotEmpty($metadata['changes']['attributes']['before']);
        $this->assertSame([], $metadata['changes']['attributes']['after']);
        $this->assertNotEmpty($metadata['changes']['attribute_groups']['before']);
        $this->assertSame([], $metadata['changes']['attribute_groups']['after']);
    }

    public function test_reference_create_and_delete_preserve_safe_values(): void
    {
        $actor = User::factory()->create();
        $service = $this->app->make(AttributeGroupManagementService::class);
        $group = $service->create($actor, ['name' => 'Physical properties', 'slug' => 'physical-properties', 'description' => 'Common properties', 'sort_order' => 4]);
        $service->delete($actor, $group);
        foreach (['attribute-group.created', 'attribute-group.deleted'] as $action) {
            $metadata = AuditLog::query()->where('action', $action)->sole()->metadata;
            $this->assertSame('Physical properties', $metadata['values']['title']);
            $this->assertSame(4, $metadata['values']['sort_order']);
        }
    }

    public function test_reference_update_audit_rolls_back_with_the_owning_transaction(): void
    {
        $actor = User::factory()->create();
        $group = AttributeGroup::factory()->create(['name' => 'Original properties']);

        try {
            DB::transaction(function () use ($actor, $group): void {
                $this->app->make(AttributeGroupManagementService::class)->update($actor, $group, ['name' => 'Rolled back properties']);
                throw new RuntimeException('Simulated owner failure');
            });
            $this->fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated owner failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame('Original properties', $group->fresh()->name);
    }
}
