<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_manage_select_attribute_and_its_options(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $group = AttributeGroup::factory()->create();
        $created = $this->actingAs($actor)->postJson('/api/v1/admin/attributes', [
            'attribute_group_id' => $group->id,
            'name' => 'Surface finish',
            'slug' => 'surface-finish',
            'type' => 'select',
            'is_filterable' => true,
            'options' => [
                ['value' => 'matte', 'label' => 'Matte', 'sort_order' => 10],
                ['value' => 'glossy', 'label' => 'Glossy', 'sort_order' => 20],
            ],
        ])->assertCreated()->assertJsonPath('data.options.0.value', 'matte');
        $id = $created->json('data.id');

        $this->actingAs($actor)->getJson('/api/v1/admin/attributes')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$id}", [
            'type' => 'multiselect',
            'options' => [['value' => 'satin', 'label' => 'Satin', 'sort_order' => 5]],
        ])->assertOk()->assertJsonPath('data.type', 'multiselect')->assertJsonPath('data.options.0.value', 'satin');

        $this->actingAs($actor)->deleteJson("/api/v1/admin/attributes/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('attributes', ['id' => $id]);
        $this->assertDatabaseMissing('attribute_options', ['attribute_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attribute.created', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attribute.updated', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attribute.deleted', 'entity_id' => $id]);
    }

    public function test_select_attribute_requires_options_and_text_attribute_rejects_them(): void
    {
        $actor = $this->userWithRole('catalog-manager');

        $this->actingAs($actor)->postJson('/api/v1/admin/attributes', ['name' => 'Color', 'slug' => 'color', 'type' => 'select', 'options' => []])
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')->assertJsonPath('error.details.options.0', 'validation.required_if');
        $this->actingAs($actor)->postJson('/api/v1/admin/attributes', ['name' => 'Material', 'slug' => 'material', 'type' => 'text', 'options' => [['value' => 'porcelain', 'label' => 'Porcelain']]])
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')->assertJsonPath('error.details.options.0', 'Options are available only for select and multiselect attributes.');
    }

    public function test_changing_an_attribute_to_a_choice_type_requires_replacement_options(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $attribute = Attribute::factory()->create(['type' => 'text']);

        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$attribute->id}", ['type' => 'select'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.options.0', 'validation.required_if');

        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$attribute->id}", [
            'type' => 'multiselect',
            'options' => [['value' => 'warm', 'label' => 'Warm']],
        ])->assertOk()->assertJsonPath('data.type', 'multiselect')->assertJsonPath('data.options.0.value', 'warm');
    }

    public function test_analyst_cannot_access_attributes(): void
    {
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/attributes')->assertForbidden();
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
