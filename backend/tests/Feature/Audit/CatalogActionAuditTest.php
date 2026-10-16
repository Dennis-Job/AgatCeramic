<?php

namespace Tests\Feature\Audit;

use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductAttributeValueManagementService;
use App\Services\ProductGroupManagementService;
use App\Services\ProductImageManagementService;
use App\Services\ProductManagementService;
use App\Services\ProductRelationManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogActionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_changes_preserve_sku_and_skip_unchanged_saves(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Before', 'price' => '100.00', 'is_active' => false]);
        $service = app(ProductManagementService::class);
        $service->update($actor, $product, ['name' => 'After', 'price' => '125.50']);
        $log = AuditLog::query()->where('action', 'product.updated')->sole();
        $this->assertSame($product->sku, data_get($log->entity_snapshot, 'sku'));
        $this->assertSame(['before' => 'Before', 'after' => 'After'], data_get($log->metadata, 'changes.title'));
        $this->assertSame(['before' => '100.00', 'after' => '125.50'], data_get($log->metadata, 'changes.price'));
        $service->update($actor, $product, ['name' => 'After', 'price' => '125.50']);
        $this->assertSame(1, AuditLog::query()->where('action', 'product.updated')->count());
    }

    public function test_rolled_back_product_update_leaves_no_audit_event(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create(['is_active' => false]);
        DB::beginTransaction();
        app(ProductManagementService::class)->update($actor, $product, ['name' => 'Rolled back']);
        DB::rollBack();
        $this->assertSame(0, AuditLog::query()->where('action', 'product.updated')->count());
        $this->assertNotSame('Rolled back', $product->refresh()->name);
    }

    public function test_image_primary_switch_records_both_changed_images_and_skips_no_op(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create();
        $first = $this->image($product, 'first.png', true);
        $second = $this->image($product, 'second.png', false);
        $service = app(ProductImageManagementService::class);
        $service->update($actor, $product, $second, ['is_primary' => true]);
        foreach ([$first->id => [true, false], $second->id => [false, true]] as $id => [$before, $after]) {
            $log = AuditLog::query()->where('action', 'product.image-updated')->where('entity_id', $id)->sole();
            $this->assertSame(['before' => $before, 'after' => $after], data_get($log->metadata, 'changes.is_primary'));
            $this->assertSame($product->sku, data_get($log->entity_snapshot, 'sku'));
        }
        $service->update($actor, $product, $second, ['is_primary' => true]);
        $this->assertSame(2, AuditLog::query()->where('action', 'product.image-updated')->count());
    }

    public function test_primary_image_upload_and_delete_preserve_automatic_changes(): void
    {
        Storage::fake('public');
        $actor = User::factory()->create();
        $product = Product::factory()->create();
        $first = $this->image($product, 'first.png', true);
        $service = app(ProductImageManagementService::class);
        $uploaded = $service->create($actor, $product, UploadedFile::fake()->image('photo.png'), ['is_primary' => true]);
        $demotion = AuditLog::query()->where('action', 'product.image-updated')->where('entity_id', $first->id)->sole();
        $this->assertSame(false, data_get($demotion->metadata, 'changes.is_primary.after'));
        $service->delete($actor, $product, $uploaded);
        $deleted = AuditLog::query()->where('action', 'product.image-deleted')->sole();
        $this->assertSame($uploaded->path, data_get($deleted->metadata, 'values.path'));
        $this->assertSame(2, AuditLog::query()->where('action', 'product.image-updated')->where('entity_id', $first->id)->count());
        $this->assertTrue($first->refresh()->is_primary);
    }

    public function test_bulk_group_diff_and_disband_use_composition_before_detach(): void
    {
        [$actor, $axis, $products, $group] = $this->groupContext(3);
        $service = app(ProductGroupManagementService::class);
        $service->applyBulk($actor, [[
            'action' => 'update', 'group_id' => $group->id, 'name' => $group->name, 'code' => $group->code,
            'axis_attribute_ids' => [$axis->id], 'product_ids' => [$products[0]->id, $products[2]->id],
        ]]);
        $log = AuditLog::query()->where('action', 'product-group.updated')->sole();
        $before = data_get($log->metadata, 'changes.product_skus.before');
        $this->assertEqualsCanonicalizing([$products[0]->sku, $products[1]->sku], $before);
        $this->assertEqualsCanonicalizing([$products[0]->sku, $products[2]->sku], data_get($log->metadata, 'changes.product_skus.after'));
        $service->applyBulk($actor, [['action' => 'disband', 'group_id' => $group->id]]);
        $deleted = AuditLog::query()->where('action', 'product-group.deleted')->sole();
        $this->assertEqualsCanonicalizing([$products[0]->sku, $products[2]->sku], data_get($deleted->metadata, 'values.product_skus'));
    }

    public function test_no_op_bulk_group_save_and_invalid_composition_create_no_false_events(): void
    {
        [$actor, $axis, $products, $group] = $this->groupContext(2);
        $change = ['action' => 'update', 'group_id' => $group->id, 'name' => $group->name, 'code' => $group->code,
            'axis_attribute_ids' => [$axis->id], 'product_ids' => array_map(fn (Product $product): int => $product->id, $products)];
        app(ProductGroupManagementService::class)->applyBulk($actor, [$change]);
        $this->assertSame(0, AuditLog::query()->where('action', 'product-group.updated')->count());
        try {
            app(ProductGroupManagementService::class)->applyBulk($actor, [[...$change, 'product_ids' => [$products[0]->id]]]);
            $this->fail('Expected invalid composition.');
        } catch (ValidationException) {
            $this->assertSame(2, $group->products()->count());
            $this->assertSame(0, AuditLog::query()->count());
        }
    }

    public function test_product_delete_records_implicitly_dissolved_group_with_original_members(): void
    {
        [$actor, , $products, $group] = $this->groupContext(2);
        app(ProductManagementService::class)->delete($actor, $products[0]);
        $log = AuditLog::query()->where('action', 'product-group.deleted')->sole();
        $this->assertSame($group->id, $log->entity_id);
        $this->assertEqualsCanonicalizing(array_map(fn (Product $product): ?string => $product->sku, $products), data_get($log->metadata, 'values.product_skus'));
        $deleted = AuditLog::query()->where('action', 'product.deleted')->sole();
        $this->assertSame($products[0]->sku, data_get($deleted->entity_snapshot, 'sku'));
    }

    public function test_shared_attribute_sync_records_each_changed_product_and_no_op_is_quiet(): void
    {
        [$actor, $axis, $products] = $this->groupContext(2);
        $shared = Attribute::factory()->create(['type' => 'string']);
        $products[0]->category()->firstOrFail()->attributes()->attach($shared->id);
        foreach ($products as $product) {
            $product->attributeValues()->create(['attribute_id' => $shared->id, 'value' => 'old']);
        }
        $payload = [['attribute_id' => $axis->id, 'value' => 'value-0'], ['attribute_id' => $shared->id, 'value' => 'new']];
        $service = app(ProductAttributeValueManagementService::class);
        $service->replace($actor, $products[0], $payload);
        foreach ($products as $product) {
            $log = AuditLog::query()->where('action', 'product.attributes-updated')->where('entity_id', $product->id)->sole();
            $this->assertSame($product->sku, data_get($log->entity_snapshot, 'sku'));
            $this->assertIsArray(data_get($log->metadata, 'changes.attribute_values'));
            $this->assertStringContainsString('old', json_encode(data_get($log->metadata, 'changes.attribute_values.before'), JSON_THROW_ON_ERROR));
            $this->assertStringContainsString('new', json_encode(data_get($log->metadata, 'changes.attribute_values.after'), JSON_THROW_ON_ERROR));
        }
        $service->replace($actor, $products[0], $payload);
        $this->assertSame(2, AuditLog::query()->where('action', 'product.attributes-updated')->count());
    }

    public function test_relation_replacement_records_old_and_new_targets_and_skips_no_op(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create();
        $old = Product::factory()->create();
        $new = Product::factory()->create();
        $product->outgoingRelations()->create(['related_product_id' => $old->id, 'type' => 'related', 'sort_order' => 0]);
        $service = app(ProductRelationManagementService::class);
        $service->replace($actor, $product, [['related_product_id' => $new->id, 'type' => 'related']]);
        $log = AuditLog::query()->where('action', 'product.relations-updated')->sole();
        $this->assertSame($old->sku, data_get($log->metadata, 'changes.relations.before.0.sku'));
        $this->assertSame($new->sku, data_get($log->metadata, 'changes.relations.after.0.sku'));
        $service->replace($actor, $product, [['related_product_id' => $new->id, 'type' => 'related']]);
        $this->assertSame(1, AuditLog::query()->where('action', 'product.relations-updated')->count());
    }

    private function image(Product $product, string $path, bool $primary): ProductImage
    {
        return $product->images()->create(['disk' => 'public', 'path' => $path, 'mime_type' => 'image/png', 'size' => 20, 'alt' => 'Tile', 'is_primary' => $primary, 'sort_order' => 0]);
    }

    /** @return array{User, Attribute, list<Product>, ProductGroup} */
    private function groupContext(int $count): array
    {
        $actor = User::factory()->create();
        $first = Product::factory()->create(['is_active' => false]);
        $products = [$first];
        for ($i = 1; $i < $count; $i++) {
            $products[] = Product::factory()->create(['category_id' => $first->category_id, 'brand_id' => $first->brand_id, 'is_active' => false]);
        }
        $axis = Attribute::factory()->create(['type' => 'string']);
        $first->category()->firstOrFail()->attributes()->attach($axis->id);
        foreach ($products as $i => $product) {
            $product->attributeValues()->create(['attribute_id' => $axis->id, 'value' => 'value-'.$i]);
        }
        $group = ProductGroup::factory()->create();
        $group->axes()->attach($axis->id);
        $group->products()->attach([$products[0]->id, $products[1]->id]);

        return [$actor, $axis, $products, $group];
    }
}
