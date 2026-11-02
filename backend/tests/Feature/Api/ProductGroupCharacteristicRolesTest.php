<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductGroupCharacteristicRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_packaging_can_differ_without_becoming_an_axis_and_is_preserved_on_edits(): void
    {
        [$actor, $category, $color, $pack, $material, $first, $second] = $this->fixtures();
        $groupId = $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Formats', 'code' => 'formats', 'axis_attribute_ids' => [$color->id],
            'individual_attribute_ids' => [$pack->id], 'product_ids' => [$first->id, $second->id],
        ])->assertCreated()->assertJsonPath('data.individual_attributes.0.id', $pack->id)
            ->assertJsonCount(1, 'data.axes')->json('data.id');

        $this->actingAs($actor)->getJson('/api/v1/admin/product-groups')
            ->assertOk()->assertJsonPath('data.0.individual_attributes.0.id', $pack->id);
        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$first->id}/attributes", [
            'attributes' => [
                ['attribute_id' => $color->id, 'value' => 'blue'],
                ['attribute_id' => $pack->id, 'value' => 0.72],
                ['attribute_id' => $material->id, 'value' => 'ceramic'],
            ],
        ])->assertOk();
        $this->assertSame(0.72, $this->value($first, $pack));
        $this->assertSame(1.08, $this->value($second, $pack));
        $this->assertSame('ceramic', $this->value($second, $material));

        $this->actingAs($actor)->patchJson("/api/v1/admin/product-groups/{$groupId}", ['name' => 'Renamed'])
            ->assertOk()->assertJsonPath('data.individual_attributes.0.id', $pack->id);
        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$first->id}/attributes", [
            'attributes' => [
                ['attribute_id' => $color->id, 'value' => 'blue'],
                ['attribute_id' => $material->id, 'value' => 'ceramic'],
            ],
        ])->assertOk();
        $this->assertNull($this->value($first, $pack));
        $this->assertSame(1.08, $this->value($second, $pack));
        $this->assertDatabaseHas('product_group_individual_attributes', ['product_group_id' => $groupId, 'attribute_id' => $pack->id]);
    }

    public function test_common_role_transition_rejects_mismatches_without_overwriting_product_values(): void
    {
        [$actor, $category, $color, $pack, $material, $first, $second] = $this->fixtures();
        $groupId = $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Formats', 'code' => 'formats', 'axis_attribute_ids' => [$color->id],
            'individual_attribute_ids' => [$pack->id], 'product_ids' => [$first->id, $second->id],
        ])->assertCreated()->json('data.id');

        $this->actingAs($actor)->patchJson("/api/v1/admin/product-groups/{$groupId}", ['individual_attribute_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('product_ids', 'error.details');
        $this->assertSame(1.44, $this->value($first, $pack));
        $this->assertSame(1.08, $this->value($second, $pack));
        $this->assertDatabaseHas('product_group_individual_attributes', ['product_group_id' => $groupId, 'attribute_id' => $pack->id]);

        $first->attributeValues()->where('attribute_id', $pack->id)->update(['value' => json_encode(1.08)]);
        $this->actingAs($actor)->patchJson("/api/v1/admin/product-groups/{$groupId}", ['individual_attribute_ids' => []])
            ->assertOk()->assertJsonCount(0, 'data.individual_attributes');
        $audit = AuditLog::query()->where('action', 'product-group.updated')->latest('id')->firstOrFail();
        $this->assertSame([$pack->id], $audit->metadata['changes']['individual_attribute_ids']['before']);
        $this->assertSame([], $audit->metadata['changes']['individual_attribute_ids']['after']);
    }

    public function test_individual_roles_support_text_and_multiselect_but_reject_invalid_assignments(): void
    {
        [$actor, $category, $color, $pack, $material, $first, $second] = $this->fixtures();
        $text = Attribute::factory()->create(['type' => 'text']);
        $multiple = Attribute::factory()->create(['type' => 'multiselect']);
        $category->attributes()->attach([$text->id, $multiple->id]);
        $payload = [
            'name' => 'Formats', 'code' => 'formats', 'axis_attribute_ids' => [$color->id],
            'product_ids' => [$first->id, $second->id],
        ];
        foreach ([[$color->id], [Attribute::factory()->create()->id]] as $invalid) {
            $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [...$payload, 'individual_attribute_ids' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('individual_attribute_ids', 'error.details');
        }
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [...$payload, 'individual_attribute_ids' => [$pack->id, $pack->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('individual_attribute_ids.0', 'error.details');
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [...$payload, 'individual_attribute_ids' => ['pack' => $pack->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('individual_attribute_ids', 'error.details');
        $reserved = Attribute::factory()->create(['slug' => 'kollektsiya']);
        $category->attributes()->attach($reserved->id);
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [...$payload, 'individual_attribute_ids' => [$reserved->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('individual_attribute_ids', 'error.details');
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            ...$payload, 'individual_attribute_ids' => [$pack->id, $text->id, $multiple->id],
        ])->assertCreated()->assertJsonCount(3, 'data.individual_attributes');
    }

    public function test_old_payloads_keep_non_axis_attributes_shared_and_duplicate_axis_values_are_allowed(): void
    {
        [$actor, $category, $color, $pack, $material, $first, $second] = $this->fixtures();
        $first->attributeValues()->where('attribute_id', $pack->id)->update(['value' => json_encode(1.08)]);
        $second->attributeValues()->where('attribute_id', $color->id)->update(['value' => json_encode('red')]);
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Formats', 'code' => 'formats', 'axis_attribute_ids' => [$color->id],
            'product_ids' => [$first->id, $second->id],
        ])->assertCreated()->assertJsonCount(0, 'data.individual_attributes');
        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$first->id}/attributes", [
            'attributes' => [
                ['attribute_id' => $color->id, 'value' => 'red'],
                ['attribute_id' => $pack->id, 'value' => 0.72],
                ['attribute_id' => $material->id, 'value' => 'porcelain'],
            ],
        ])->assertOk();
        $this->assertSame(0.72, $this->value($second, $pack));
    }

    public function test_optional_individual_role_cannot_be_detached_from_category_while_group_uses_it(): void
    {
        [$actor, $category, $color, $pack, $material, $first, $second] = $this->fixtures();
        $optional = Attribute::factory()->create(['type' => 'text']);
        $category->attributes()->attach($optional->id);
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Formats', 'code' => 'formats', 'axis_attribute_ids' => [$color->id],
            'individual_attribute_ids' => [$pack->id, $optional->id], 'product_ids' => [$first->id, $second->id],
        ])->assertCreated();
        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attributes", [
            'attributes' => array_map(fn (Attribute $attribute): array => ['id' => $attribute->id], [$color, $pack, $material]),
        ])->assertUnprocessable()->assertJsonValidationErrors('attributes', 'error.details');
        $this->actingAs($actor)->deleteJson("/api/v1/admin/attributes/{$optional->id}")->assertUnprocessable();
    }

    /** @return array{User, Category, Attribute, Attribute, Attribute, Product, Product} */
    private function fixtures(): array
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());
        $category = Category::factory()->create();
        $color = Attribute::factory()->create(['type' => 'string']);
        $pack = Attribute::factory()->create(['type' => 'decimal']);
        $material = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach([$color->id, $pack->id, $material->id]);
        $products = Product::factory()->count(2)->create(['category_id' => $category->id, 'brand_id' => null]);
        foreach ($products as $index => $product) {
            foreach ([[$color, $index === 0 ? 'red' : 'blue'], [$pack, $index === 0 ? 1.44 : 1.08], [$material, 'porcelain']] as [$attribute, $value]) {
                $product->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => $value]);
            }
        }

        return [$actor, $category, $color, $pack, $material, $products[0], $products[1]];
    }

    private function value(Product $product, Attribute $attribute): mixed
    {
        return ProductAttributeValue::query()->where('product_id', $product->id)->where('attribute_id', $attribute->id)->first()?->value;
    }
}
