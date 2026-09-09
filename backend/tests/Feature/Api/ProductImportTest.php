<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessProductImport;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ImportDispatchTask;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductGroup;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
use App\Services\ProductManagementService;
use App\Services\StorageCleanupService;
use App\Support\ProductWorkbookSchema;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_constraints_are_checked_before_any_catalogue_writes(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['slug' => 'tile']);
        $axis = Attribute::factory()->create(['slug' => 'size', 'type' => 'integer']);
        $shared = Attribute::factory()->create(['slug' => 'color', 'type' => 'string']);
        $category->attributes()->attach([$axis->id => ['is_required' => false], $shared->id => ['is_required' => true]]);
        $group = ProductGroup::factory()->create();
        $group->axes()->attach($axis);
        $products = Product::factory()->count(2)->create(['category_id' => $category->id, 'brand_id' => null, 'is_active' => true]);
        foreach ($products as $index => $product) {
            $group->products()->attach($product);
            ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $axis->id, 'value' => $index + 1]);
            ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $shared->id, 'value' => 'white']);
        }
        $this->mock(ProductManagementService::class, function ($mock): void {
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
        });
        $headers = [...ProductWorkbookSchema::BASE_HEADERS, 'attribute.size', 'attribute.color'];
        $base = ['name' => 'Tile', 'category_slug' => 'tile', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false, 'attribute.color' => 'white'];
        foreach ([['attribute.size' => 2], ['attribute.size' => null], ['attribute.size' => 1, 'attribute.color' => null]] as $invalid) {
            $path = $this->workbook([$headers], [
                $this->row($headers, $base + ['slug' => 'new-tile']),
                $this->row($headers, array_replace($base, ['id' => $products[0]->id, 'sku' => $products[0]->sku, 'slug' => $products[0]->slug], $invalid)),
            ]);
            try {
                app(ProductImportService::class)->import($actor, $path);
                $this->fail('Group constraints must be checked before the first write.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('Строка 3:', collect($exception->errors())->flatten()->first());
            } finally {
                unlink($path);
            }
        }
    }

    public function test_preflight_preserves_ordered_unique_value_handoffs(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['slug' => 'tile']);
        $existing = Product::factory()->create(['category_id' => $category->id, 'slug' => 'old-slug', 'article_number' => 'old-article', 'barcode' => '12345678']);
        $headers = ProductWorkbookSchema::BASE_HEADERS;
        $base = ['name' => 'Tile', 'category_slug' => 'tile', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false];
        $path = $this->workbook([$headers], [
            $this->row($headers, $base + ['id' => $existing->id, 'sku' => $existing->sku, 'slug' => 'new-slug']),
            $this->row($headers, $base + ['slug' => 'old-slug', 'article_number' => 'old-article', 'barcode' => '12345678']),
        ]);
        try {
            $this->assertSame(['created' => 1, 'updated' => 1, 'processed' => 2], app(ProductImportService::class)->import($actor, $path));
            $this->assertSame('new-slug', $existing->fresh()->slug);
            $this->assertDatabaseHas('products', ['slug' => 'old-slug', 'article_number' => 'old-article', 'barcode' => '12345678']);
        } finally {
            unlink($path);
        }
    }

    public function test_temporary_sku_matching_a_newly_allocated_sku_does_not_change_create_to_update(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        Category::factory()->create(['slug' => 'tile', 'sku_prefix' => '7']);
        $headers = ProductWorkbookSchema::BASE_HEADERS;
        $base = ['name' => 'Tile', 'category_slug' => 'tile', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false];
        $path = $this->workbook([$headers], [
            $this->row($headers, $base + ['slug' => 'tile-one']),
            $this->row($headers, $base + ['slug' => 'tile-two', 'sku' => '7000001']),
        ]);
        try {
            $this->assertSame(['created' => 2, 'updated' => 0, 'processed' => 2], app(ProductImportService::class)->import($actor, $path));
            $this->assertDatabaseCount('products', 2);
        } finally {
            unlink($path);
        }
    }

    public function test_preflight_rejects_invalid_later_rows_before_calling_catalogue_writers(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        Category::factory()->create(['slug' => 'tile']);
        $this->mock(ProductManagementService::class, function ($mock): void {
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
        });
        $headers = ProductWorkbookSchema::BASE_HEADERS;
        $base = ['name' => 'Tile', 'slug' => 'tile-one', 'category_slug' => 'tile', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false];
        foreach ([
            ['slug' => 'tile-two', 'category_slug' => 'missing'],
            ['slug' => 'tile-two', 'stock_quantity' => 2147483648],
            ['slug' => 'tile-two', 'old_price' => 5],
            ['slug' => 'tile-one'],
        ] as $invalid) {
            $path = $this->workbook([$headers], [$this->row($headers, $base), $this->row($headers, array_replace($base, $invalid))]);
            try {
                app(ProductImportService::class)->import($actor, $path);
                $this->fail('Preflight must reject the workbook before writing its valid first row.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('Строка 3:', collect($exception->errors())->flatten()->first());
            } finally {
                unlink($path);
            }
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_preflight_validates_category_assignment_options_and_required_values_before_writes(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['slug' => 'tile']);
        $color = Attribute::factory()->create(['slug' => 'color', 'type' => 'select']);
        $color->options()->create(['label' => 'White', 'value' => 'white']);
        $category->attributes()->attach($color->id, ['is_required' => true]);
        $this->mock(ProductManagementService::class, fn ($mock) => $mock->shouldNotReceive('create'));
        $headers = [...ProductWorkbookSchema::BASE_HEADERS, 'attribute.color'];
        $base = ['name' => 'Tile', 'slug' => 'tile-one', 'category_slug' => 'tile', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => true, 'is_on_sale' => false, 'attribute.color' => 'white'];
        foreach (['unknown', null] as $value) {
            $path = $this->workbook([$headers], [$this->row($headers, $base), $this->row($headers, array_replace($base, ['slug' => 'tile-two', 'attribute.color' => $value]))]);
            try {
                app(ProductImportService::class)->import($actor, $path);
                $this->fail('Invalid category attributes must fail preflight.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('Строка 3:', collect($exception->errors())->flatten()->first());
            } finally {
                unlink($path);
            }
        }
    }

    public function test_import_manager_can_upload_a_private_xlsx_and_poll_its_status(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = $this->userWithPermission('imports.manage');
        $path = $this->workbook([ProductWorkbookSchema::BASE_HEADERS], []);

        $response = $this->actingAs($actor)->post('/api/v1/admin/products/import', [
            'file' => new UploadedFile($path, 'catalog.xlsx', ProductExportService::CONTENT_TYPE, null, true),
        ])->assertAccepted()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.filename', 'catalog.xlsx');

        $import = ProductImport::query()->findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($import->path);
        $this->assertDatabaseHas('import_dispatch_tasks', [
            'import_type' => ImportDispatchTask::TYPE_PRODUCT,
            'import_id' => $import->id,
            'status' => 'dispatched',
        ]);
        Queue::assertPushed(ProcessProductImport::class, fn (ProcessProductImport $job): bool => $job->productImportId === $import->id);

        $this->actingAs($actor)->getJson("/api/v1/admin/product-imports/{$import->id}")
            ->assertOk()->assertJsonPath('data.status', 'pending');
        $other = $this->userWithPermission('imports.manage');
        $this->actingAs($other)->getJson("/api/v1/admin/product-imports/{$import->id}")->assertNotFound();
    }

    public function test_import_job_updates_existing_products_creates_new_products_and_imports_attributes(): void
    {
        Storage::fake('local');
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['name' => 'Плитка', 'slug' => 'tile', 'sku_prefix' => '7']);
        $color = Attribute::factory()->create(['name' => 'Цвет', 'slug' => 'color', 'type' => 'select']);
        $color->options()->createMany([
            ['label' => 'Белый', 'value' => 'white', 'sort_order' => 0],
            ['label' => 'Серый', 'value' => 'gray', 'sort_order' => 1],
        ]);
        $features = Attribute::factory()->create(['name' => 'Свойства', 'slug' => 'features', 'type' => 'multiselect']);
        $features->options()->createMany([
            ['label' => 'Матовая', 'value' => 'matte', 'sort_order' => 0],
            ['label' => 'Морозостойкая', 'value' => 'frost', 'sort_order' => 1],
        ]);
        $category->attributes()->attach([
            $color->id => ['sort_order' => 0, 'is_required' => true],
            $features->id => ['sort_order' => 1, 'is_required' => false],
        ]);
        $existing = Product::factory()->create([
            'category_id' => $category->id,
            'sku' => '7000050',
            'name' => 'Старое название',
            'slug' => 'existing-tile',
            'is_active' => true,
        ]);
        ProductAttributeValue::query()->create(['product_id' => $existing->id, 'attribute_id' => $color->id, 'value' => 'white']);

        $headers = [...ProductWorkbookSchema::BASE_HEADERS, 'attribute.color', 'attribute.features'];
        $rows = [
            $this->row($headers, [
                'id' => $existing->id, 'sku' => '7000050', 'name' => 'Новое название', 'slug' => 'existing-tile',
                'category_id' => $category->id, 'category_slug' => 'tile', 'unit' => 'piece', 'price' => 150.25,
                'stock_quantity' => 8, 'is_active' => true, 'is_on_sale' => true, 'attribute.color' => 'gray',
            ]),
            $this->row($headers, [
                'name' => 'Новый товар', 'slug' => 'new-tile', 'category_slug' => 'tile', 'unit' => 'square_meter',
                'price' => 210, 'stock_quantity' => 3, 'is_active' => true, 'is_on_sale' => false,
                'barcode' => '0012345678901', 'attribute.color' => 'white', 'attribute.features' => '["matte","frost"]',
            ]),
        ];
        $import = $this->storedImport($actor, $this->workbook([$headers], $rows));

        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));

        $import->refresh();
        $this->assertSame('completed', $import->status);
        $this->assertSame(1, $import->created_rows);
        $this->assertSame(1, $import->updated_rows);
        $this->assertSame(2, $import->processed_rows);
        $this->assertDatabaseHas('storage_cleanup_tasks', ['disk' => 'local', 'path' => $import->path]);
        $existing->refresh();
        $this->assertSame('Новое название', $existing->name);
        $this->assertTrue($existing->is_on_sale);
        $this->assertSame('gray', $existing->attributeValues()->where('attribute_id', $color->id)->value('value'));
        $created = Product::query()->where('slug', 'new-tile')->sole();
        $this->assertMatchesRegularExpression('/^7\d{6}$/', $created->sku);
        $this->assertSame('0012345678901', $created->barcode);
        $this->assertTrue($created->is_active);
        $this->assertSame(['matte', 'frost'], $created->attributeValues()->where('attribute_id', $features->id)->value('value'));
    }

    public function test_generic_import_reports_every_invalid_row_without_changing_the_catalogue(): void
    {
        Storage::fake('local');
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['slug' => 'tile', 'sku_prefix' => '8']);
        $headers = ProductWorkbookSchema::BASE_HEADERS;
        $rows = [
            $this->row($headers, ['name' => 'Valid', 'slug' => 'valid', 'category_slug' => 'tile', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false]),
            $this->row($headers, ['name' => 'Invalid', 'slug' => 'invalid', 'category_slug' => 'missing', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false]),
            $this->row($headers, ['name' => 'Invalid price', 'slug' => 'invalid-price', 'category_slug' => 'tile', 'unit' => 'piece', 'price' => -1, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false]),
        ];
        $import = $this->storedImport($actor, $this->workbook([$headers], $rows));
        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));

        $this->assertDatabaseMissing('products', ['slug' => 'valid']);
        $import->refresh();
        $this->assertSame('completed', $import->status);
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(2, $import->failed_rows);
        $this->assertSame(3, $import->processed_rows);
        $this->assertNull($import->error_message);
        $this->assertSame(['Invalid', 'Invalid price'], $import->rowErrors()->pluck('name')->all());
        $this->assertStringContainsString('категория', $import->rowErrors()->first()->messages[0]);
        $this->assertDatabaseHas('storage_cleanup_tasks', ['disk' => 'local', 'path' => $import->path]);
        $this->actingAs($actor)->getJson("/api/v1/admin/product-imports/{$import->id}")
            ->assertOk()->assertJsonPath('data.has_error_file', true)->assertJsonCount(2, 'data.row_errors');
        $this->actingAs($actor)->get("/api/v1/admin/product-imports/{$import->id}/errors")
            ->assertOk()->assertHeader('content-disposition', "attachment; filename=product-import-{$import->id}-errors.xlsx");
    }

    public function test_generic_import_uses_durable_queue_items_and_resumes_after_a_chunk(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = $this->userWithPermission('imports.manage');
        Category::factory()->create(['slug' => 'tile', 'sku_prefix' => '8']);
        $headers = ProductWorkbookSchema::BASE_HEADERS;
        $rows = array_map(fn (int $number): array => $this->row($headers, [
            'name' => 'Tile '.$number,
            'slug' => 'tile-'.$number,
            'category_slug' => 'tile',
            'unit' => 'piece',
            'price' => 10,
            'stock_quantity' => 1,
            'is_active' => false,
            'is_on_sale' => false,
        ]), range(1, 101));
        $import = $this->storedImport($actor, $this->workbook([$headers], $rows));

        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));
        $import->refresh();
        $this->assertSame('processing', $import->status);
        $this->assertSame(100, $import->processed_rows);
        $this->assertDatabaseCount('products', 100);
        $this->assertDatabaseHas('product_import_items', ['product_import_id' => $import->id, 'status' => 'pending']);
        Queue::assertPushed(ProcessProductImport::class, fn (ProcessProductImport $job): bool => $job->productImportId === $import->id);

        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));
        $import->refresh();
        $this->assertSame('completed', $import->status);
        $this->assertSame(101, $import->processed_rows);
        $this->assertSame(101, $import->created_rows);
        $this->assertDatabaseCount('products', 101);
        $this->assertDatabaseMissing('product_import_items', ['product_import_id' => $import->id, 'status' => 'pending']);
    }

    public function test_localized_export_roundtrips_manager_edits_and_seo_without_changing_sku(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['name' => 'Плитка', 'slug' => 'tile']);
        $color = Attribute::factory()->create(['name' => 'Цвет', 'slug' => 'color', 'type' => 'select', 'unit' => null]);
        $color->options()->createMany([
            ['label' => 'Белый', 'value' => 'white'],
            ['label' => 'Серый', 'value' => 'gray'],
        ]);
        $features = Attribute::factory()->create(['name' => 'Свойства', 'slug' => 'features', 'type' => 'multiselect', 'unit' => null]);
        $features->options()->createMany([
            ['label' => 'Матовая', 'value' => 'matte'],
            ['label' => 'Морозостойкая', 'value' => 'frost'],
        ]);
        $weight = Attribute::factory()->create(['name' => 'Вес', 'slug' => 'weight', 'type' => 'decimal', 'unit' => 'кг']);
        $available = Attribute::factory()->create(['name' => 'Под заказ', 'slug' => 'on-demand', 'type' => 'boolean', 'unit' => null]);
        $category->attributes()->attach([$color->id, $features->id, $weight->id, $available->id]);
        $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => null, 'is_active' => false]);
        foreach ([$color->id => 'white', $features->id => ['matte'], $weight->id => 12.5, $available->id => false] as $attributeId => $value) {
            ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attributeId, 'value' => $value]);
        }
        $sku = $product->sku;
        $export = app(ProductExportService::class)->create([]);
        $path = $this->editedExport($export['path'], [
            'Товары' => ['Название' => 'Обновлённая плитка', 'Цена' => 77.5, 'Старая цена' => null, 'Единица продажи' => 'Квадратный метр', 'Активность' => 'Нет', 'Распродажа' => 'Да', 'Цвет' => 'Серый', 'Свойства' => 'Матовая; Морозостойкая', 'Вес (кг)' => 20.25, 'Под заказ' => 'Да'],
            'SEO товаров' => ['URL товара (slug)' => 'updated-tile'],
        ]);

        $result = app(ProductImportService::class)->import($actor, $path);

        $this->assertSame(['created' => 0, 'updated' => 1, 'processed' => 1], $result);
        $product->refresh();
        $this->assertSame($sku, $product->sku);
        $this->assertSame('Обновлённая плитка', $product->name);
        $this->assertSame('updated-tile', $product->slug);
        $this->assertSame('square_meter', $product->unit);
        $this->assertSame('77.50', $product->price);
        $this->assertFalse($product->is_active);
        $this->assertTrue($product->is_on_sale);
        $values = $product->attributeValues()->get()->keyBy('attribute_id');
        $this->assertSame('gray', $values[$color->id]->value);
        $this->assertSame(['matte', 'frost'], $values[$features->id]->value);
        $this->assertSame(20.25, $values[$weight->id]->value);
        $this->assertTrue($values[$available->id]->value);
    }

    public function test_localized_import_applies_category_and_brand_names_edited_on_manager_sheet(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['name' => 'Плитка']);
        $newCategory = Category::factory()->create(['name' => 'Мозаика']);
        $brand = Brand::factory()->create(['name' => 'Первый бренд']);
        $newBrand = Brand::factory()->create(['name' => 'Другой бренд']);
        $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => false]);
        $export = app(ProductExportService::class)->create([]);
        $path = $this->editedExport($export['path'], ['Товары' => ['Категория' => 'Мозаика', 'Бренд' => 'Другой бренд']]);

        app(ProductImportService::class)->import($actor, $path);

        $this->assertSame($newCategory->id, $product->refresh()->category_id);
        $this->assertSame($newBrand->id, $product->brand_id);
    }

    public function test_localized_multiselect_roundtrips_labels_with_semicolons_and_quotes(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create();
        $features = Attribute::factory()->create(['name' => 'Свойства', 'slug' => 'features', 'type' => 'multiselect', 'unit' => null]);
        $features->options()->createMany([
            ['label' => 'Матовая', 'value' => 'matte'],
            ['label' => 'Фактура; "камень"', 'value' => 'stone'],
        ]);
        $category->attributes()->attach($features);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $features->id, 'value' => ['matte', 'stone']]);
        $export = app(ProductExportService::class)->create([]);

        try {
            app(ProductImportService::class)->import($actor, $export['path']);
        } finally {
            unlink($export['path']);
        }

        $this->assertSame(['matte', 'stone'], $product->attributeValues()->where('attribute_id', $features->id)->value('value'));
    }

    public function test_localized_import_creates_a_product_with_a_matching_seo_row_and_server_generated_sku(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['sku_prefix' => '8']);
        $original = Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);
        $export = app(ProductExportService::class)->create([]);
        $path = $this->editedExport($export['path'], [
            'Товары' => ['SKU' => 'NEW-1', 'Название' => 'Новая плитка', 'Артикул' => null, 'Штрихкод' => null],
            'SEO товаров' => ['SKU' => 'NEW-1', 'Название' => 'Новая плитка', 'URL товара (slug)' => 'new-localized-tile'],
        ]);

        $result = app(ProductImportService::class)->import($actor, $path);

        $this->assertSame(['created' => 1, 'updated' => 0, 'processed' => 1], $result);
        $created = Product::query()->where('slug', 'new-localized-tile')->sole();
        $this->assertSame('Новая плитка', $created->name);
        $this->assertMatchesRegularExpression('/^8\d{6}$/', $created->sku);
        $this->assertNotSame($original->sku, $created->sku);
        $this->assertDatabaseHas('products', ['id' => $original->id, 'sku' => $original->sku, 'slug' => $original->slug]);
    }

    public function test_localized_import_rejects_an_unknown_sku_without_a_matching_seo_row(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $product = Product::factory()->create(['is_active' => false]);
        $export = app(ProductExportService::class)->create([]);
        $path = $this->editedExport($export['path'], ['Товары' => ['SKU' => 'UNKNOWN-SKU']]);

        try {
            app(ProductImportService::class)->import($actor, $path);
            $this->fail('Changing only the SKU must not create a duplicate product.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('SKU существующего товара нельзя изменять', $exception->errors()['file'][0]);
        }

        $this->assertDatabaseCount('products', 1);
        $this->assertSame($product->sku, $product->refresh()->sku);
    }

    public function test_localized_import_rejects_ambiguous_option_labels_and_rolls_back(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create();
        $color = Attribute::factory()->create(['name' => 'Цвет', 'slug' => 'color', 'type' => 'select', 'unit' => null]);
        $color->options()->createMany([
            ['label' => 'Белый', 'value' => 'white'],
            ['label' => 'Белый', 'value' => 'ivory'],
        ]);
        $category->attributes()->attach($color);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => false, 'name' => 'Исходное название']);
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $color->id, 'value' => 'white']);
        $export = app(ProductExportService::class)->create([]);
        $path = $this->editedExport($export['path'], ['Товары' => ['Название' => 'Не должно сохраниться']]);

        try {
            app(ProductImportService::class)->import($actor, $path);
            $this->fail('Ambiguous labels must not be guessed.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('неоднозначно', $exception->errors()['file'][0]);
        }
        $this->assertSame('Исходное название', $product->refresh()->name);
    }

    public function test_import_requires_the_dedicated_permission_and_a_valid_xlsx(): void
    {
        Storage::fake('local');
        Queue::fake();
        $firstPath = $this->workbook([ProductWorkbookSchema::BASE_HEADERS], []);
        $secondPath = $this->workbook([ProductWorkbookSchema::BASE_HEADERS], []);

        $this->post('/api/v1/admin/products/import', [
            'file' => new UploadedFile($firstPath, 'products.xlsx', ProductExportService::CONTENT_TYPE, null, true),
        ], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->actingAs($this->userWithPermission('catalog.manage'))
            ->post('/api/v1/admin/products/import', [
                'file' => new UploadedFile($secondPath, 'products.xlsx', ProductExportService::CONTENT_TYPE, null, true),
            ], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->userWithPermission('imports.manage'))
            ->post('/api/v1/admin/products/import', [
                'file' => UploadedFile::fake()->createWithContent('products.csv', 'not an xlsx'),
            ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['file']]]);
    }

    /** @param list<string> $headers
     * @param  array<string, mixed>  $values
     * @return list<mixed>
     */
    private function row(array $headers, array $values): array
    {
        return array_map(static fn (string $header): mixed => $values[$header] ?? null, $headers);
    }

    /** @param list<list<string>> $headerRows
     * @param  list<list<mixed>>  $rows
     */
    private function workbook(array $headerRows, array $rows): string
    {
        $path = tempnam(storage_path('framework/testing'), 'product-import-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($headerRows as $headers) {
            $writer->addRow(Row::fromValues($headers));
        }
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    /** @param array<string, array<string, mixed>> $edits */
    private function editedExport(string $source, array $edits): string
    {
        $path = tempnam(storage_path('framework/testing'), 'localized-import-');
        $reader = new Reader;
        $writer = new Writer;
        $reader->open($source);
        $writer->openToFile($path);
        $first = true;
        foreach ($reader->getSheetIterator() as $sheet) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $first = false;
            $writer->getCurrentSheet()->setName($sheet->getName());
            $headers = [];
            foreach ($sheet->getRowIterator() as $index => $row) {
                $values = $row->toArray();
                if ($index === 1) {
                    $headers = $values;
                } else {
                    foreach ($edits[$sheet->getName()] ?? [] as $header => $value) {
                        $column = array_search($header, $headers, true);
                        $this->assertNotFalse($column, 'Expected exported column '.$header);
                        $values[$column] = $value;
                    }
                }
                $writer->addRow(Row::fromValues($values));
            }
        }
        $writer->close();
        $reader->close();
        unlink($source);

        return $path;
    }

    private function storedImport(User $actor, string $sourcePath): ProductImport
    {
        $path = 'product-imports/'.basename($sourcePath).'.xlsx';
        Storage::disk('local')->put($path, file_get_contents($sourcePath));

        return ProductImport::query()->create([
            'user_id' => $actor->id,
            'original_filename' => 'products.xlsx',
            'disk' => 'local',
            'path' => $path,
            'status' => 'pending',
        ]);
    }

    private function userWithPermission(string $code): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', $code)->sole());
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
