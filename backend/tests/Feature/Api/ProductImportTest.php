<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessProductImport;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
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
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_invalid_row_rolls_back_the_whole_import_and_can_be_marked_failed(): void
    {
        Storage::fake('local');
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['slug' => 'tile', 'sku_prefix' => '8']);
        $headers = ProductWorkbookSchema::BASE_HEADERS;
        $rows = [
            $this->row($headers, ['name' => 'Valid', 'slug' => 'valid', 'category_slug' => 'tile', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false]),
            $this->row($headers, ['name' => 'Invalid', 'slug' => 'invalid', 'category_slug' => 'missing', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 1, 'is_active' => false, 'is_on_sale' => false]),
        ];
        $import = $this->storedImport($actor, $this->workbook([$headers], $rows));
        $job = new ProcessProductImport($import->id);

        try {
            $job->handle(app(ProductImportService::class), app(StorageCleanupService::class));
            $this->fail('The invalid workbook should fail.');
        } catch (ValidationException $exception) {
            $job->failed($exception);
        }

        $this->assertDatabaseMissing('products', ['slug' => 'valid']);
        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('Строка 3', $import->error_message);
        $this->assertDatabaseHas('storage_cleanup_tasks', ['disk' => 'local', 'path' => $import->path]);
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
