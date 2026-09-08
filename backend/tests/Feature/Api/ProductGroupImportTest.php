<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessProductImport;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductExportService;
use App\Services\ProductGroupImportService;
use App\Services\ProductImportService;
use App\Services\StorageCleanupService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;
use ZipArchive;

class ProductGroupImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_download_requires_both_import_and_catalog_permissions_and_contains_instruction_sheet(): void
    {
        $both = $this->actor(['imports.manage', 'catalog.manage']);
        $this->actingAs($both)->get('/api/v1/admin/products/group-import-template')->assertOk();
        $onlyImport = $this->actor(['imports.manage']);
        $this->actingAs($onlyImport)->get('/api/v1/admin/products/group-import-template')->assertForbidden();
        Attribute::factory()->create(['name' => 'Цвет', 'type' => 'string']);
        $file = app(ProductGroupImportService::class)->createTemplate();
        $reader = new Reader;
        $reader->open($file['path']);
        $sheets = iterator_to_array($reader->getSheetIterator());
        $this->assertSame(['Группы', 'Состав', 'Инструкция'], collect($sheets)->map->getName()->values()->all());
        $reader->close();
        $zip = new ZipArchive;
        $zip->open($file['path']);
        $this->assertStringContainsString('<dataValidations', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('$Z$2:$Z$2', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('hidden="1"', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('Цвет', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringNotContainsString('definedNames', $zip->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString('Скачайте свежую выгрузку', $zip->getFromName('xl/worksheets/sheet3.xml'));
        $this->assertStringNotContainsString('rgb="23456B"', $zip->getFromName('xl/styles.xml'));
        $this->assertStringContainsString('rgb="FF23456B"', $zip->getFromName('xl/styles.xml'));
        $zip->close();
        @unlink($file['path']);
    }

    public function test_workbook_creates_updates_moves_and_disbands_groups_atomically(): void
    {
        Storage::fake('local');
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        [$category, $axis, $products] = $this->products(4);
        $old = ProductGroup::factory()->create(['code' => 'OLD', 'name' => 'Old']);
        $old->axes()->attach($axis->id);
        $old->products()->attach([$products[0]->id, $products[1]->id]);
        $other = ProductGroup::factory()->create(['code' => 'OTHER', 'name' => 'Other']);
        $other->axes()->attach($axis->id);
        $other->products()->attach([$products[2]->id, $products[3]->id]);
        $path = $this->workbook([
            'Группы' => [
                ['Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название', 'Ось 1', 'Ось 2', 'Ось 3', 'Ось 4', 'Ось 5', 'Ось 6', 'Ось 7', 'Ось 8', 'Ось 9', 'Ось 10', 'Ось 11', 'Ось 12', 'Ось 13', 'Ось 14', 'Ось 15', 'Ось 16', 'Ось 17', 'Ось 18', 'Ось 19', 'Ось 20'],
                ['Изменить', 'OLD', 'OLD', 'NEW', 'New', $axis->id.': '.$axis->name],
                ['Расформировать', 'OTHER', 'OTHER', '', ''],
            ],
            'Состав' => [['Ключ группы', 'SKU'], ['OLD', $products[0]->sku], ['OLD', $products[2]->sku]],
        ]);
        $disk = 'product-group-imports/test.xlsx';
        Storage::disk('local')->put($disk, file_get_contents($path));
        @unlink($path);
        $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'groups.xlsx', 'disk' => 'local', 'path' => $disk, 'status' => 'pending', 'operation' => 'group']);
        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));
        $this->assertDatabaseHas('product_groups', ['code' => 'NEW', 'name' => 'New']);
        $this->assertDatabaseMissing('product_groups', ['code' => 'OTHER']);
        $this->assertDatabaseHas('product_group_members', ['product_id' => $products[2]->id, 'product_group_id' => ProductGroup::query()->where('code', 'NEW')->sole()->id]);
        $this->assertSame('completed', $import->fresh()->status);
    }

    public function test_upload_queues_group_operation(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        $path = $this->workbook(['Группы' => [['Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название', 'Ось 1', 'Ось 2', 'Ось 3', 'Ось 4', 'Ось 5', 'Ось 6', 'Ось 7', 'Ось 8', 'Ось 9', 'Ось 10', 'Ось 11', 'Ось 12', 'Ось 13', 'Ось 14', 'Ось 15', 'Ось 16', 'Ось 17', 'Ось 18', 'Ось 19', 'Ось 20']], 'Состав' => [['Ключ группы', 'SKU']]]);
        $response = $this->actingAs($actor)->post('/api/v1/admin/products/group-import', ['file' => new UploadedFile($path, 'groups.xlsx', ProductExportService::CONTENT_TYPE, null, true)])->assertAccepted()->assertJsonPath('data.operation', 'group');
        Queue::assertPushed(ProcessProductImport::class, fn (ProcessProductImport $job) => $job->productImportId === $response->json('data.id'));
        @unlink($path);
    }

    public function test_invalid_composition_is_reported_without_changing_any_group(): void
    {
        Storage::fake('local');
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        [, $axis, $products] = $this->products(2);
        $path = $this->workbook([
            'Группы' => [
                ['Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название', 'Ось 1', 'Ось 2', 'Ось 3', 'Ось 4', 'Ось 5', 'Ось 6', 'Ось 7', 'Ось 8', 'Ось 9', 'Ось 10', 'Ось 11', 'Ось 12', 'Ось 13', 'Ось 14', 'Ось 15', 'Ось 16', 'Ось 17', 'Ось 18', 'Ось 19', 'Ось 20'],
                ['Создать', 'BAD', '', 'BAD', 'Bad', $axis->id.': '.$axis->name],
            ],
            'Состав' => [['Ключ группы', 'SKU'], ['BAD', $products[0]->sku], ['BAD', 'MISSING']],
        ]);
        $disk = 'product-group-imports/bad.xlsx';
        Storage::disk('local')->put($disk, file_get_contents($path));
        @unlink($path);
        $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'bad.xlsx', 'disk' => 'local', 'path' => $disk, 'status' => 'pending', 'operation' => 'group']);
        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));
        $this->assertSame('completed', $import->fresh()->status);
        $this->assertSame(1, $import->fresh()->failed_rows);
        $this->assertDatabaseMissing('product_groups', ['code' => 'BAD']);
    }

    /** @return array{Category, Attribute, array<int, Product>} */
    private function products(int $count): array
    {
        $category = Category::factory()->create();
        $axis = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach($axis->id);
        $products = [];
        foreach (range(1, $count) as $index) {
            $p = Product::factory()->create(['category_id' => $category->id, 'brand_id' => null, 'sku' => 'SKU-'.$index]);
            $p->attributeValues()->create(['attribute_id' => $axis->id, 'value' => 'v'.$index]);
            $products[] = $p;
        }

        return [$category, $axis, $products];
    }

    /** @param array<string, list<list<mixed>>> $sheets */
    private function workbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'groups-');
        $writer = new Writer;
        $writer->openToFile($path);
        $first = true;
        foreach ($sheets as $name => $rows) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            } $first = false;
            $writer->getCurrentSheet()->setName($name);
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }
        $writer->close();

        return $path;
    }

    private function actor(array $permissions): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $permissions)->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
