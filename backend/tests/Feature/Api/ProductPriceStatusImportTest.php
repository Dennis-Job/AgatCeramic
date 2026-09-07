<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessProductImport;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductExportService;
use App\Services\ProductImportService;
use App\Services\ProductPriceStatusImportService;
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

class ProductPriceStatusImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_template_has_exactly_the_three_safe_update_sheets(): void
    {
        $file = app(ProductPriceStatusImportService::class)->createTemplate();
        $reader = new Reader;
        $reader->open($file['path']);
        $this->assertSame(['Цены', 'Активность', 'Распродажа'], collect($reader->getSheetIterator())->map->getName()->values()->all());
        $reader->close();
        $zip = new ZipArchive;
        $zip->open($file['path']);
        foreach ([2, 3] as $sheet) {
            $xml = $zip->getFromName("xl/worksheets/sheet{$sheet}.xml");
            $this->assertNotFalse($xml);
            $this->assertStringContainsString('<dataValidations', $xml);
            $this->assertLessThan(strpos($xml, '<legacyDrawing'), strpos($xml, '<dataValidations'));
        }
        $zip->close();
        @unlink($file['path']);
    }

    public function test_template_download_and_upload_are_limited_to_import_managers(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = $this->actor();
        $this->actingAs($actor)->get('/api/v1/admin/products/price-status-template')->assertOk();
        $path = $this->workbook(['Цены' => [['SKU *', 'Цена *', 'Старая цена']]]);
        $response = $this->actingAs($actor)->post('/api/v1/admin/products/price-status-import', ['file' => new UploadedFile($path, 'prices.xlsx', ProductExportService::CONTENT_TYPE, null, true)])
            ->assertAccepted()->assertJsonPath('data.operation', 'price_status');
        $import = ProductImport::query()->findOrFail($response->json('data.id'));
        Queue::assertPushed(ProcessProductImport::class, fn (ProcessProductImport $job) => $job->productImportId === $import->id);
    }

    public function test_price_and_status_sheets_update_only_their_target_fields_and_keep_row_errors(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $product = Product::factory()->create(['sku' => '1000001', 'price' => 100, 'old_price' => null, 'is_active' => false, 'is_on_sale' => false, 'name' => 'Не менять']);
        $path = $this->workbook([
            'Цены' => [['SKU *', 'Цена *', 'Старая цена'], ['1000001', 80, 120], ['missing', 90, null]],
            'Активность' => [['SKU *', 'Активность *'], ['1000001', 'Нет']],
            'Распродажа' => [['SKU *', 'Распродажа *'], ['1000001', 'Да']],
        ]);
        $diskPath = 'product-price-status-imports/test.xlsx';
        Storage::disk('local')->put($diskPath, file_get_contents($path));
        @unlink($path);
        $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'prices.xlsx', 'disk' => 'local', 'path' => $diskPath, 'status' => 'pending', 'operation' => 'price_status']);
        (new ProcessProductImport($import->id))->handle(app(ProductImportService::class), app(StorageCleanupService::class));
        $product->refresh();
        $import->refresh();
        $this->assertSame('80.00', $product->price);
        $this->assertSame('120.00', $product->old_price);
        $this->assertFalse($product->is_active);
        $this->assertTrue($product->is_on_sale);
        $this->assertSame('Не менять', $product->name);
        $this->assertSame('completed', $import->status);
        $this->assertSame(1, $import->failed_rows);
        $this->assertSame(3, $import->updated_rows);
        $this->actingAs($actor)->get("/api/v1/admin/product-price-status-imports/{$import->id}/errors")->assertOk();
    }

    /** @param array<string, list<list<mixed>>> $sheets */
    private function workbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'price-status-');
        $writer = new Writer;
        $writer->openToFile($path);
        $first = true;
        foreach ($sheets as $name => $rows) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            } $first = false;
            $writer->getCurrentSheet()->setName($name);
            foreach ($rows as $values) {
                $writer->addRow(Row::fromValues($values));
            }
        }
        $writer->close();

        return $path;
    }

    private function actor(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', 'imports.manage')->sole());
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
