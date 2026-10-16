<?php

namespace Tests\Feature\Audit;

use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\ProductImageImport;
use App\Models\ProductImport;
use App\Models\User;
use App\Queries\ProductQuery;
use App\Services\CategoryProductImportService;
use App\Services\GenericProductImportService;
use App\Services\ImportLifecycleService;
use App\Services\ImportSubmissionService;
use App\Services\ProductExportService;
use App\Services\ProductGroupImportService;
use App\Services\ProductImageImportService;
use App\Services\ProductImportExecutor;
use App\Services\ProductImportTemplateService;
use App\Services\ProductPriceStatusImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ImportExportAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_records_safe_context_with_the_original_actor(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = User::factory()->create();
        $service = app(ImportSubmissionService::class);
        $import = $service->submitProductWorkbook($actor, UploadedFile::fake()->create('private-contact@example.test.xlsx'), 'product-imports', operation: 'price_status');
        $imageImport = $service->submitProductImageArchive($actor, UploadedFile::fake()->create('private-contact@example.test.zip'));

        $event = AuditLog::query()->where('action', 'import.submitted')->sole();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame($import->id, $event->entity_id);
        $this->assertSame(['import_id' => $import->id, 'operation' => 'price_status'], $event->metadata);
        $imageEvent = AuditLog::query()->where('action', 'image-import.submitted')->sole();
        $this->assertSame($actor->id, $imageEvent->actor_id);
        $this->assertSame($imageImport->id, $imageEvent->entity_id);
        $this->assertStringNotContainsString('private-contact', $event->toJson().$imageEvent->toJson());
    }

    public function test_completion_is_idempotent_and_keeps_the_initiator_and_actual_counts(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create();
        $this->actingAs(User::factory()->create());
        $import = $this->productImport($actor, ['status' => 'processing', 'operation' => 'price_status', 'total_rows' => 4, 'processed_rows' => 4, 'created_rows' => 0, 'updated_rows' => 3, 'failed_rows' => 1]);
        $service = app(ImportLifecycleService::class);
        $service->completeProductImport($import);
        $service->completeProductImport($import);
        $service->failProductImport($import->id, 'private-contact@example.test');

        $event = AuditLog::query()->where('action', 'import.completed')->sole();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame(['import_id' => $import->id, 'operation' => 'price_status', 'total' => 4, 'processed' => 4, 'created' => 0, 'updated' => 3, 'failed' => 1], $event->metadata);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'import.failed']);
    }

    public function test_terminal_event_keeps_submission_actor_after_employee_deletion(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = User::factory()->create(['name' => 'Инициатор импорта']);
        $import = app(ImportSubmissionService::class)->submitProductWorkbook($actor, UploadedFile::fake()->create('source.xlsx'), 'product-imports');
        $actor->delete();

        app(ImportLifecycleService::class)->failProductImport($import->id, 'Инициатор больше недоступен.');
        $event = AuditLog::query()->where('action', 'import.failed')->sole();

        $this->assertSame(['name' => 'Инициатор импорта'], $event->actor_snapshot);
        $this->assertSame($import->id, $event->entity_id);
    }

    public function test_preflight_completed_import_still_has_one_completion_event(): void
    {
        Storage::fake('local');
        $import = $this->productImport(User::factory()->create(), ['status' => 'completed', 'total_rows' => 2, 'processed_rows' => 2, 'failed_rows' => 2]);
        $service = app(ImportLifecycleService::class);
        $service->completeProductImport($import);
        $service->completeProductImport($import);

        $this->assertSame(1, AuditLog::query()->where('action', 'import.completed')->count());
    }

    public function test_failure_is_idempotent_and_does_not_leak_error_text_or_become_completed(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create();
        $import = $this->productImport($actor);
        $service = app(ImportLifecycleService::class);
        $service->failProductImport($import->id, 'private-contact@example.test +79991234567');
        $service->failProductImport($import->id, 'retry failure');
        $service->completeProductImport($import);

        $event = AuditLog::query()->where('action', 'import.failed')->sole();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('failed', $import->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'import.completed']);
        $this->assertStringNotContainsString('private-contact', $event->toJson());
        $this->assertArrayNotHasKey('error_message', $event->metadata);
    }

    public function test_image_terminal_events_preserve_counts_actor_and_retry_idempotency(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create();
        $completed = $this->imageImport($actor, ['total_folders' => 3, 'processed_folders' => 3, 'created_images' => 4, 'replaced_images' => 2, 'failed_folders' => 1]);
        $failed = $this->imageImport($actor);
        $service = app(ImportLifecycleService::class);
        $service->completeProductImageImport($completed);
        $service->completeProductImageImport($completed);
        $service->failProductImageImport($failed->id, 'private-contact@example.test');
        $service->failProductImageImport($failed->id, 'retry');
        $service->completeProductImageImport($failed);

        $event = AuditLog::query()->where('action', 'image-import.completed')->sole();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame(['import_id' => $completed->id, 'operation' => 'image', 'total' => 3, 'processed' => 3, 'created' => 4, 'replaced' => 2, 'failed' => 1], $event->metadata);
        $failure = AuditLog::query()->where('action', 'image-import.failed')->sole();
        $this->assertSame($actor->id, $failure->actor_id);
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertStringNotContainsString('private-contact', $failure->toJson());
    }

    public function test_export_records_explicit_actor_and_filtered_product_count_only_after_success(): void
    {
        $actor = User::factory()->create();
        $products = Product::factory()->count(3)->create();
        $export = app(ProductExportService::class)->create(['skus' => [$products[0]->sku, $products[1]->sku]], $actor);
        try {
            $this->assertFileExists($export['path']);
            $event = AuditLog::query()->where('action', 'product.exported')->sole();
            $this->assertSame($actor->id, $event->actor_id);
            $this->assertSame(['affected_records' => 2], $event->metadata);
        } finally {
            unlink($export['path']);
        }
    }

    public function test_failed_export_removes_its_file_and_has_no_success_event(): void
    {
        $before = glob(storage_path('app/product-export-*')) ?: [];
        $this->mock(ProductQuery::class, function ($mock): void {
            $mock->shouldReceive('maximumImageCount')->andReturn(0);
            $mock->shouldReceive('filtered')->andThrow(new RuntimeException('Export query failed.'));
        });
        try {
            app(ProductExportService::class)->create([], User::factory()->create());
            $this->fail('A failed export must throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Export query failed.', $exception->getMessage());
        }
        $this->assertSame($before, glob(storage_path('app/product-export-*')) ?: []);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'product.exported']);
    }

    public function test_generic_import_mutations_link_to_import_and_retry_keeps_one_change(): void
    {
        $actor = User::factory()->create();
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach($attribute);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);
        $import = $this->productImport($actor, ['total_rows' => 1]);
        $payload = $product->only(['category_id', 'brand_id', 'name', 'slug', 'description', 'article_number', 'barcode', 'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale']);
        $payload['name'] = 'Changed by import';
        $import->items()->create(['product_id' => $product->id, 'row_number' => 2, 'name' => $product->name, 'payload' => $payload, 'attribute_payload' => [['attribute_id' => $attribute->id, 'value' => 'white']], 'status' => 'pending']);

        $service = app(GenericProductImportService::class);
        $this->assertTrue($service->process($import));
        $this->assertTrue($service->process($import));

        $event = AuditLog::query()->where('action', 'product.updated')->sole();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame($import->id, $event->metadata['import_id'] ?? null);
        $this->assertSame('white', $event->metadata['changes']['attribute_values']['after'][0]['value'] ?? null);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'product.attributes-updated']);
    }

    public function test_price_status_import_mutation_links_to_import(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create(['is_active' => false, 'price' => '10.00']);
        $import = $this->productImport($actor, ['operation' => 'price_status', 'total_rows' => 1]);
        $import->items()->create(['product_id' => $product->id, 'row_number' => 2, 'payload' => ['updates' => ['price' => '20.00']], 'attribute_payload' => [], 'status' => 'pending']);
        app(ProductPriceStatusImportService::class)->process($import);

        $event = AuditLog::query()->where('action', 'product.updated')->sole();
        $this->assertSame($import->id, $event->metadata['import_id'] ?? null);
        $this->assertSame('20.00', $product->fresh()->price);
    }

    public function test_group_import_mutation_links_to_import(): void
    {
        $actor = User::factory()->create();
        $category = Category::factory()->create();
        $axis = Attribute::factory()->create(['type' => 'integer']);
        $category->attributes()->attach($axis);
        $products = Product::factory()->count(2)->create(['category_id' => $category->id, 'brand_id' => null]);
        foreach ($products as $index => $product) {
            $product->attributeValues()->create(['attribute_id' => $axis->id, 'value' => $index + 1]);
        }
        $import = $this->productImport($actor, ['operation' => 'group', 'total_rows' => 1]);
        $import->items()->create(['row_number' => 1, 'payload' => ['changes' => [['action' => 'create', 'group_id' => null, 'name' => 'Imported group', 'code' => 'import-group', 'axis_attribute_ids' => [$axis->id], 'product_ids' => $products->modelKeys()]]], 'attribute_payload' => [], 'status' => 'pending']);
        app(ProductGroupImportService::class)->process($import);

        $event = AuditLog::query()->where('action', 'product-group.created')->sole();
        $this->assertSame($import->id, $event->metadata['import_id'] ?? null);
        $this->assertSame($actor->id, $event->actor_id);
    }

    public function test_category_template_mutation_links_to_import(): void
    {
        $actor = User::factory()->create();
        $category = Category::factory()->create();
        $import = $this->productImport($actor, ['category_id' => $category->id]);
        $template = app(ProductImportTemplateService::class)->create($category, [['name' => 'Imported tile', 'unit' => 'piece', 'price' => '10.00']]);
        try {
            $this->assertTrue(app(CategoryProductImportService::class)->process($import, $template['path']));
            $event = AuditLog::query()->where('action', 'product.created')->sole();
            $this->assertSame($import->id, $event->metadata['import_id'] ?? null);
        } finally {
            unlink($template['path']);
        }
    }

    public function test_image_import_records_gallery_and_primary_switch_once_with_parent_sku(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $actor = User::factory()->create();
        $product = Product::factory()->create(['sku' => '6000011']);
        $oldPrimary = ProductImage::query()->create(['product_id' => $product->id, 'disk' => 'public', 'path' => "product-images/{$product->id}/6000011_3.png", 'mime_type' => 'image/png', 'size' => 1, 'is_primary' => true, 'sort_order' => 3]);
        $import = $this->imageImport($actor);
        Storage::disk('local')->makeDirectory('imports');
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($import->path), ZipArchive::CREATE);
        $zip->addFromString('6000011/6000011_1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4WQAAAAASUVORK5CYII='));
        $zip->close();
        $service = app(ProductImageImportService::class);
        $this->assertTrue($service->process($import));
        $this->assertTrue($service->process($import));

        $event = AuditLog::query()->where('action', 'product.image-imported')->sole();
        $this->assertSame('6000011', $event->entity_snapshot['sku']);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame($import->id, $event->metadata['import_id']);
        $this->assertSame(1, $event->metadata['created']);
        $before = $event->metadata['changes']['images']['before'] ?? [];
        $this->assertSame($oldPrimary->id, $before[0]['image_id']);
        $this->assertTrue($before[0]['is_primary']);
        $after = $event->metadata['changes']['images']['after'] ?? [];
        $this->assertCount(2, $after);
        $this->assertFalse($after[0]['is_primary']);
        $this->assertTrue($after[1]['is_primary']);
    }

    public function test_unchanged_active_import_has_no_mutation_events(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $payload = $product->only(['category_id', 'brand_id', 'name', 'slug', 'description', 'article_number', 'barcode', 'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale']);
        DB::transaction(fn () => app(ProductImportExecutor::class)->apply($actor, $product->id, $payload, []));

        $this->assertSame(0, AuditLog::query()->count());
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_changed_active_import_has_one_final_diff_without_temporary_activation_changes(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true, 'price' => '10.00']);
        $payload = $product->only(['category_id', 'brand_id', 'name', 'slug', 'description', 'article_number', 'barcode', 'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale']);
        $payload['price'] = '20.00';
        $result = DB::transaction(fn () => app(ProductImportExecutor::class)->applyWithResult($actor, $product->id, $payload, [], ['import_id' => 25]));

        $event = AuditLog::query()->where('action', 'product.updated')->sole();
        $this->assertSame(['price' => ['before' => '10.00', 'after' => '20.00']], $event->metadata['changes']);
        $this->assertSame(25, $event->metadata['import_id']);
        $this->assertTrue($result['product']->is_active);
    }

    public function test_new_active_import_records_created_values_after_activation(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->make(['category_id' => Category::factory()->create()->id, 'brand_id' => null, 'is_active' => true]);
        $payload = $product->only(['category_id', 'brand_id', 'name', 'slug', 'description', 'article_number', 'barcode', 'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale']);
        $result = DB::transaction(fn () => app(ProductImportExecutor::class)->applyWithResult($actor, null, $payload, []));

        $event = AuditLog::query()->where('action', 'product.created')->sole();
        $this->assertTrue($event->metadata['values']['is_active']);
        $this->assertTrue($result['product']->is_active);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_import_aggregation_keeps_audit_for_synchronized_group_members(): void
    {
        $actor = User::factory()->create();
        $category = Category::factory()->create();
        $axis = Attribute::factory()->create(['type' => 'integer']);
        $shared = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach([$axis->id, $shared->id]);
        $products = Product::factory()->count(2)->create(['category_id' => $category->id, 'brand_id' => null, 'is_active' => false]);
        $group = ProductGroup::factory()->create();
        $group->axes()->attach($axis);
        $group->products()->attach($products->modelKeys());
        foreach ($products as $index => $product) {
            $product->attributeValues()->create(['attribute_id' => $axis->id, 'value' => $index + 1]);
            $product->attributeValues()->create(['attribute_id' => $shared->id, 'value' => 'white']);
        }
        $source = $products[0];
        $payload = $source->only(['category_id', 'brand_id', 'name', 'slug', 'description', 'article_number', 'barcode', 'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale']);
        DB::transaction(fn () => app(ProductImportExecutor::class)->apply($actor, $source->id, $payload, [['attribute_id' => $axis->id, 'value' => 1], ['attribute_id' => $shared->id, 'value' => 'black']], ['import_id' => 25]));

        $sourceEvent = AuditLog::query()->where('action', 'product.updated')->sole();
        $peerEvent = AuditLog::query()->where('action', 'product.attributes-updated')->sole();
        $this->assertSame($source->id, $sourceEvent->entity_id);
        $this->assertSame($products[1]->id, $peerEvent->entity_id);
        $this->assertSame(25, $peerEvent->metadata['import_id']);
        $this->assertTrue($peerEvent->metadata['synchronized']);
        $this->assertSame('black', $products[1]->attributeValues()->where('attribute_id', $shared->id)->sole()->value);
        $this->assertSame(2, AuditLog::query()->count());
    }

    public function test_image_replacement_audit_preserves_image_id_and_generated_file_change(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $actor = User::factory()->create();
        $product = Product::factory()->create(['sku' => '6000011']);
        $image = ProductImage::query()->create(['product_id' => $product->id, 'disk' => 'public', 'path' => "product-images/{$product->id}/6000011_1.png", 'mime_type' => 'image/png', 'size' => 1, 'is_primary' => true, 'sort_order' => 1]);
        $import = $this->imageImport($actor);
        Storage::disk('local')->makeDirectory('imports');
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($import->path), ZipArchive::CREATE);
        $zip->addFromString('6000011/6000011_1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4WQAAAAASUVORK5CYII='));
        $zip->close();
        app(ProductImageImportService::class)->process($import);

        $event = AuditLog::query()->where('action', 'product.image-imported')->sole();
        $before = $event->metadata['changes']['images']['before'][0] ?? [];
        $after = $event->metadata['changes']['images']['after'][0] ?? [];
        $this->assertSame(1, $event->metadata['replaced']);
        $this->assertSame($image->id, $before['image_id'] ?? null);
        $this->assertSame($image->id, $after['image_id'] ?? null);
        $this->assertSame('6000011_1.png', $before['file'] ?? null);
        $this->assertSame("6000011_1-i{$import->id}.png", $after['file'] ?? null);
        $this->assertSame('image/png', $after['mime_type']);
        $this->assertGreaterThan(1, $after['size']);
    }

    /** @param array<string, mixed> $attributes */
    private function productImport(User $actor, array $attributes = []): ProductImport
    {
        return ProductImport::query()->create(array_replace(['user_id' => $actor->id, 'original_filename' => 'private-contact@example.test.xlsx', 'disk' => 'local', 'path' => 'imports/source.xlsx', 'status' => 'pending'], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function imageImport(User $actor, array $attributes = []): ProductImageImport
    {
        return ProductImageImport::query()->create(array_replace(['user_id' => $actor->id, 'original_filename' => 'private-contact@example.test.zip', 'disk' => 'local', 'path' => 'imports/source.zip', 'status' => 'processing'], $attributes));
    }
}
