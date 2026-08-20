<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\AttributeOption;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
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

    public function test_options_without_a_type_are_validated_against_the_current_attribute_type(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $attribute = Attribute::factory()->create(['type' => 'text']);

        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$attribute->id}", [
            'options' => [['value' => 'ignored', 'label' => 'Ignored']],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['options']]]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$attribute->id}", [
            'options' => [],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['options']]]);
    }

    public function test_option_replacement_rejects_values_used_by_products_or_variants_and_allows_compatible_changes(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create(['type' => 'select']);
        foreach (['matte', 'glossy', 'unused'] as $index => $value) {
            AttributeOption::factory()->create([
                'attribute_id' => $attribute->id,
                'value' => $value,
                'label' => ucfirst($value),
                'sort_order' => $index,
            ]);
        }
        $category->attributes()->attach($attribute->id);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => 'matte']);
        ProductVariantAttributeValue::query()->create(['product_variant_id' => $variant->id, 'attribute_id' => $attribute->id, 'value' => 'glossy']);

        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$attribute->id}", [
            'options' => [['value' => 'matte', 'label' => 'New matte']],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['options']]]);

        $this->assertDatabaseHas('attribute_options', ['attribute_id' => $attribute->id, 'value' => 'glossy', 'label' => 'Glossy']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'attribute.updated', 'entity_id' => $attribute->id]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$attribute->id}", [
            'options' => [
                ['value' => 'matte', 'label' => 'Матовая'],
                ['value' => 'glossy', 'label' => 'Глянцевая'],
                ['value' => 'satin', 'label' => 'Сатиновая'],
            ],
        ])->assertOk()->assertJsonPath('data.options.2.value', 'satin');

        $this->assertDatabaseMissing('attribute_options', ['attribute_id' => $attribute->id, 'value' => 'unused']);
        $metadata = AuditLog::query()->where('action', 'attribute.updated')->where('entity_id', $attribute->id)->sole()->metadata;
        $this->assertTrue($metadata['options_replaced']);
        $this->assertSame(['matte', 'glossy', 'unused'], $metadata['option_values_before']);
        $this->assertSame(['matte', 'glossy', 'satin'], $metadata['option_values_after']);
    }

    public function test_type_change_is_rejected_when_any_existing_value_is_incompatible_and_audited_when_compatible(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create(['type' => 'text']);
        $category->attributes()->attach($attribute->id);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $productValue = ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => 'not-a-number']);
        ProductVariantAttributeValue::query()->create(['product_variant_id' => $variant->id, 'attribute_id' => $attribute->id, 'value' => '12.5']);

        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$attribute->id}", [
            'type' => 'number',
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['type']]]);

        $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'type' => 'text']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'attribute.updated', 'entity_id' => $attribute->id]);

        $productValue->update(['value' => '10']);
        $this->actingAs($actor)->putJson("/api/v1/admin/attributes/{$attribute->id}", [
            'type' => 'number',
        ])->assertOk()->assertJsonPath('data.type', 'number');

        $metadata = AuditLog::query()->where('action', 'attribute.updated')->where('entity_id', $attribute->id)->sole()->metadata;
        $this->assertSame('text', $metadata['type_before']);
        $this->assertSame('number', $metadata['type_after']);
    }

    public function test_attribute_with_product_or_variant_values_cannot_be_deleted(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create();
        $category->attributes()->attach($attribute->id);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $productValue = ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => 'product']);
        ProductVariantAttributeValue::query()->create(['product_variant_id' => $variant->id, 'attribute_id' => $attribute->id, 'value' => 'variant']);

        $this->actingAs($actor)->deleteJson("/api/v1/admin/attributes/{$attribute->id}")
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['attribute']]]);

        $productValue->delete();
        $this->actingAs($actor)->deleteJson("/api/v1/admin/attributes/{$attribute->id}")
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['attribute']]]);
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'attribute.deleted', 'entity_id' => $attribute->id]);
    }

    public function test_requiredness_can_be_enabled_before_values_are_filled_and_is_audited(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create(['is_required' => false]);
        $category->attributes()->attach($attribute->id);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/attributes/{$attribute->id}", [
            'is_required' => true,
        ])->assertOk()->assertJsonPath('data.is_required', true);

        $this->assertDatabaseMissing('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $attribute->id]);
        $metadata = AuditLog::query()->where('action', 'attribute.updated')->where('entity_id', $attribute->id)->sole()->metadata;
        $this->assertFalse($metadata['is_required_before']);
        $this->assertTrue($metadata['is_required_after']);
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
