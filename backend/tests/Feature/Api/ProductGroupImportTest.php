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
use App\Services\ProductGroupWorkbookReader;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        $this->assertStringContainsString('$AT$2:$AT$2', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('$AU$2:$AU$2', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('Индивидуальная 1', $zip->getFromName('xl/worksheets/sheet1.xml'));
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
        $this->app->call([new ProcessProductImport($import->id), 'handle']);
        $this->assertDatabaseHas('product_groups', ['code' => 'NEW', 'name' => 'New']);
        $this->assertDatabaseMissing('product_groups', ['code' => 'OTHER']);
        $this->assertDatabaseHas('product_group_members', ['product_id' => $products[2]->id, 'product_group_id' => ProductGroup::query()->where('code', 'NEW')->sole()->id]);
        $this->assertSame('completed', $import->fresh()->status);
        $this->assertSame(2, $import->fresh()->processed_rows);
    }

    public function test_generated_template_ignores_hidden_axis_dropdown_rows_when_reimported(): void
    {
        Storage::fake('local');
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        ProductGroup::factory()->create(['code' => 'EXISTING']);
        Attribute::factory()->count(3)->sequence(
            ['name' => 'Высота', 'type' => 'integer'],
            ['name' => 'Цвет', 'type' => 'string'],
            ['name' => 'Ширина', 'type' => 'integer'],
        )->create();
        $file = app(ProductGroupImportService::class)->createTemplate();
        $path = 'product-group-imports/generated.xlsx';
        Storage::disk('local')->put($path, file_get_contents($file['path']));
        @unlink($file['path']);
        $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'generated.xlsx', 'disk' => 'local', 'path' => $path, 'status' => 'pending', 'operation' => 'group']);

        $this->app->call([new ProcessProductImport($import->id), 'handle']);

        $this->assertSame('completed', $import->fresh()->status);
        $this->assertSame(1, $import->fresh()->total_rows);
        $this->assertSame(1, $import->fresh()->processed_rows);
        $this->assertSame(0, $import->fresh()->failed_rows);
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

    public function test_excel_saved_workbook_with_blank_helper_header_creates_four_product_group(): void
    {
        Storage::fake('local');
        Queue::fake();
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        [, $axis, $products] = $this->products(4);
        foreach ($products as $index => $product) {
            $product->update(['sku' => (string) (6000030 + $index)]);
        }
        $file = app(ProductGroupImportService::class)->createTemplate();
        $reader = new Reader;
        $reader->open($file['path']);
        $headers = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $headers = $row->toArray();
                break;
            }
            break;
        }
        $reader->close();
        @unlink($file['path']);
        $path = $this->workbook([
            'Группы' => [
                [...$headers, null],
                ['Не изменять', '1465', '1465', '1465', 'Существующая группа 1', $axis->name],
                ['Не изменять', '4578', '4578', '4578', 'Существующая группа 2', $axis->name],
                [...array_pad(['Создать', 1234567, '', 1234567, 'Раковины цвета', $axis->name], 45, null), $axis->name],
                [...array_fill(0, 45, null), 'Скрытый справочник'],
            ],
            'Состав' => [
                ['Ключ группы', 'SKU', null],
                [1234567, 6000030],
                [1234567, 6000031],
                [1234567, 6000032],
                [1234567, 6000033],
            ],
        ]);

        try {
            $response = $this->actingAs($actor)->post('/api/v1/admin/products/group-import', [
                'file' => new UploadedFile($path, 'groups.xlsx', ProductExportService::CONTENT_TYPE, null, true),
            ])->assertAccepted();
            $importId = $response->json('data.id');
            $this->app->call([new ProcessProductImport($importId), 'handle']);

            $this->actingAs($actor)->get("/api/v1/admin/product-group-imports/{$importId}")
                ->assertOk()->assertJsonPath('data.status', 'completed')
                ->assertJsonPath('data.total_rows', 3)->assertJsonPath('data.processed_rows', 3)
                ->assertJsonPath('data.failed_rows', 0)->assertJsonPath('data.created_rows', 1);
            $group = ProductGroup::query()->where('code', '1234567')->sole();
            $this->assertSame('Раковины цвета', $group->name);
            $this->assertEqualsCanonicalizing(array_map(fn (Product $product): int => $product->id, $products), $group->products->modelKeys());
            $this->assertSame([$axis->id], $group->axes->modelKeys());
            $this->app->call([new ProcessProductImport($importId), 'handle']);
            $this->assertSame(3, ProductImport::query()->findOrFail($importId)->processed_rows);
            $this->assertDatabaseCount('product_groups', 1);
        } finally {
            @unlink($path);
        }
    }

    public function test_workbook_still_rejects_changed_headers(): void
    {
        $path = $this->workbook([
            'Группы' => [
                ['Действие', 'Изменённый заголовок', 'Исходный код', 'Код группы', 'Название', 'Ось 1', 'Ось 2', 'Ось 3', 'Ось 4', 'Ось 5', 'Ось 6', 'Ось 7', 'Ось 8', 'Ось 9', 'Ось 10', 'Ось 11', 'Ось 12', 'Ось 13', 'Ось 14', 'Ось 15', 'Ось 16', 'Ось 17', 'Ось 18', 'Ось 19', 'Ось 20'],
                ['Создать', '1234567', '', '1234567', 'Раковины цвета', 'Цвет'],
            ],
            'Состав' => [['Ключ группы', 'SKU'], ['1234567', '6000030'], ['1234567', '6000031']],
        ]);

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage('Лист «Группы»: не изменяйте заголовки шаблона.');
            app(ProductGroupWorkbookReader::class)->read($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_reader_distinguishes_missing_individual_roles_from_explicit_empty_roles(): void
    {
        foreach ([false, true] as $withIndividuals) {
            $headers = $this->groupHeaders($withIndividuals);
            $path = $this->workbook([
                'Группы' => [$headers, ['Изменить', 'PACKS', 'PACKS', 'PACKS', 'Плитка', 'Цвет']],
                'Состав' => [['Ключ группы', 'SKU']],
            ]);
            try {
                $row = app(ProductGroupWorkbookReader::class)->read($path)['groups'][0];
                if ($withIndividuals) {
                    $this->assertSame([], $row['individuals']);
                } else {
                    $this->assertArrayNotHasKey('individuals', $row);
                }
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_reader_reads_individual_roles_and_ignores_new_hidden_helpers(): void
    {
        $path = $this->workbook([
            'Группы' => [
                [...$this->groupHeaders(), null, null],
                [...array_pad(['Создать', 'PACKS', '', 'PACKS', 'Плитка', 'Цвет'], 25, null), 'м² в упаковке', 'Описание упаковки'],
                [...array_fill(0, 45, null), 'Цвет', 'Описание упаковки'],
            ],
            'Состав' => [['Ключ группы', 'SKU']],
        ]);
        try {
            $zip = new ZipArchive;
            $zip->open($path);
            $xml = new DOMDocument;
            $xml->loadXML($zip->getFromName('xl/worksheets/sheet1.xml'));
            $xpath = new DOMXPath($xml);
            $row = $xpath->query('//*[local-name()="row" and @r="2"]')->item(0);
            // Excel can retain the short visible span while storing hidden helper cells farther right.
            $cell = $xml->createElement('c');
            $cell->setAttribute('r', 'AT2');
            $cell->setAttribute('t', 'inlineStr');
            $text = $xml->createElement('is');
            $text->appendChild($xml->createElement('t', 'Скрытый справочник'));
            $cell->appendChild($text);
            $row->appendChild($cell);
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml->saveXML());
            $zip->close();
            $rows = app(ProductGroupWorkbookReader::class)->read($path)['groups'];
            $this->assertCount(1, $rows);
            $this->assertSame(['Цвет'], $rows[0]['axes']);
            $this->assertSame(['м² в упаковке', 'Описание упаковки'], $rows[0]['individuals']);
        } finally {
            @unlink($path);
        }
    }

    public function test_new_workbook_creates_packaging_roles_and_keeps_them_after_resume_and_export(): void
    {
        Storage::fake('local');
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        [$category, $axis, $products] = $this->products(2);
        $area = Attribute::factory()->create(['name' => 'м² в упаковке', 'type' => 'decimal']);
        $packing = Attribute::factory()->create(['name' => 'Описание упаковки', 'type' => 'text']);
        $category->attributes()->attach([$area->id, $packing->id]);
        foreach ($products as $index => $product) {
            $product->attributeValues()->where('attribute_id', $axis->id)->update(['value' => json_encode('same-color')]);
            $product->attributeValues()->create(['attribute_id' => $area->id, 'value' => $index === 0 ? 0.72 : 1.44]);
            $product->attributeValues()->create(['attribute_id' => $packing->id, 'value' => $index === 0 ? '8 плиток' : '16 плиток']);
        }
        $path = $this->workbook([
            'Группы' => [
                $this->groupHeaders(),
                [...array_pad(['Создать', 'PACKS', '', 'PACKS', 'Разные упаковки', $axis->name], 25, null), $area->name, $packing->name],
            ],
            'Состав' => [['Ключ группы', 'SKU'], ['PACKS', $products[0]->sku], ['PACKS', $products[1]->sku]],
        ]);
        try {
            $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'roles.xlsx', 'disk' => 'local', 'path' => 'unused', 'status' => 'pending', 'operation' => 'group']);
            $service = app(ProductGroupImportService::class);
            $service->initialize($import, $path);
            $this->assertSame(0, $import->fresh()->failed_rows);
            $this->assertSame([$area->id, $packing->id], $import->items()->sole()->payload['changes'][0]['individual_attribute_ids']);
            $service->process($import);
            $service->process($import);
            $group = ProductGroup::query()->where('code', 'PACKS')->sole();
            $this->assertSame([$area->id, $packing->id], $group->individualAttributes->modelKeys());
            $this->assertSame(0.72, $products[0]->fresh()->attributeValues->firstWhere('attribute_id', $area->id)->value);
            $this->assertSame(1.44, $products[1]->fresh()->attributeValues->firstWhere('attribute_id', $area->id)->value);
            $export = $service->createTemplate();
            try {
                $row = app(ProductGroupWorkbookReader::class)->read($export['path'])['groups'][0];
                $this->assertSame([$axis->name], $row['axes']);
                $this->assertSame([$area->name, $packing->name], $row['individuals']);
            } finally {
                @unlink($export['path']);
            }
        } finally {
            @unlink($path);
        }
    }

    public function test_legacy_updates_preserve_roles_and_new_blank_roles_require_equal_shared_values(): void
    {
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        [$category, $axis, $products] = $this->products(2);
        $area = Attribute::factory()->create(['name' => 'Площадь упаковки', 'type' => 'decimal']);
        $category->attributes()->attach($area);
        $group = ProductGroup::factory()->create(['code' => 'PACKS']);
        $group->axes()->attach($axis);
        $group->individualAttributes()->attach($area);
        $group->products()->attach(array_map(fn (Product $product): int => $product->id, $products));
        foreach ($products as $index => $product) {
            $product->attributeValues()->create(['attribute_id' => $area->id, 'value' => $index + 1]);
        }

        foreach ([false, true] as $withIndividuals) {
            $path = $this->workbook([
                'Группы' => [$this->groupHeaders($withIndividuals), ['Изменить', 'PACKS', 'PACKS', 'PACKS', 'Новое название', $axis->name]],
                'Состав' => [['Ключ группы', 'SKU'], ['PACKS', $products[0]->sku], ['PACKS', $products[1]->sku]],
            ]);
            try {
                $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'roles.xlsx', 'disk' => 'local', 'path' => 'unused', 'status' => 'pending', 'operation' => 'group']);
                $service = app(ProductGroupImportService::class);
                $service->initialize($import, $path);
                $this->assertSame($withIndividuals ? 1 : 0, $import->fresh()->failed_rows);
                if (! $withIndividuals) {
                    $this->assertArrayNotHasKey('individual_attribute_ids', $import->items()->sole()->payload['changes'][0]);
                    $service->process($import);
                    $this->assertSame('Новое название', $group->fresh()->name);
                } else {
                    $this->assertSame(0, $import->items()->count());
                    $this->assertStringContainsString('Площадь упаковки', implode(' ', $import->rowErrors()->sole()->messages));
                }
                $this->assertSame([$area->id], $group->fresh()->individualAttributes->modelKeys());
            } finally {
                @unlink($path);
            }
        }

        $products[1]->attributeValues()->where('attribute_id', $area->id)->update(['value' => json_encode(1)]);
        $path = $this->workbook([
            'Группы' => [$this->groupHeaders(), ['Изменить', 'PACKS', 'PACKS', 'PACKS', 'Общая упаковка', $axis->name]],
            'Состав' => [['Ключ группы', 'SKU'], ['PACKS', $products[0]->sku], ['PACKS', $products[1]->sku]],
        ]);
        try {
            $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'clear.xlsx', 'disk' => 'local', 'path' => 'unused', 'status' => 'pending', 'operation' => 'group']);
            $service->initialize($import, $path);
            $this->assertSame([], $import->items()->sole()->payload['changes'][0]['individual_attribute_ids']);
            $service->process($import);
            $this->assertSame([], $group->fresh()->individualAttributes->modelKeys());
            $this->assertSame('Общая упаковка', $group->fresh()->name);
        } finally {
            @unlink($path);
        }
    }

    public function test_workbook_rejects_overlapping_roles_before_changing_group_and_reports_role_error(): void
    {
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        [, $axis, $products] = $this->products(2);
        $path = $this->workbook([
            'Группы' => [
                $this->groupHeaders(),
                [...array_pad(['Создать', 'BAD', '', 'BAD', 'Конфликт ролей', $axis->name], 25, null), $axis->name],
            ],
            'Состав' => [['Ключ группы', 'SKU'], ['BAD', $products[0]->sku], ['BAD', $products[1]->sku]],
        ]);
        try {
            $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'bad-roles.xlsx', 'disk' => 'local', 'path' => 'unused', 'status' => 'pending', 'operation' => 'group']);
            $service = app(ProductGroupImportService::class);
            $service->initialize($import, $path);
            $this->assertSame(1, $import->fresh()->failed_rows);
            $this->assertDatabaseMissing('product_groups', ['code' => 'BAD']);
            $this->assertSame(0, $import->items()->count());
            $report = $service->createErrorReport($import);
            try {
                $reader = new Reader;
                $reader->open($report['path']);
                $messages = [];
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $messages[] = implode(' ', $row->toArray());
                    }
                }
                $reader->close();
                $this->assertStringContainsString('Индивидуальные характеристики не должны повторяться или совпадать с осями выбора', implode(' ', $messages));
            } finally {
                @unlink($report['path']);
            }
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_composition_is_reported_without_changing_any_group(): void
    {
        Storage::fake('local');
        $actor = $this->actor(['imports.manage', 'catalog.manage']);
        [, $axis, $products] = $this->products(2);
        $path = $this->workbook([
            'Группы' => [
                ['Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название', 'Ось 1', 'Ось 2', 'Ось 3', 'Ось 4', 'Ось 5', 'Ось 6', 'Ось 7', 'Ось 8', 'Ось 9', 'Ось 10', 'Ось 11', 'Ось 12', 'Ось 13', 'Ось 14', 'Ось 15', 'Ось 16', 'Ось 17', 'Ось 18', 'Ось 19', 'Ось 20'],
                ['Не изменять', 'EXISTING', 'EXISTING', 'EXISTING', 'Existing', $axis->name],
                ['Создать', 'BAD', '', 'BAD', 'Bad', $axis->id.': '.$axis->name],
            ],
            'Состав' => [['Ключ группы', 'SKU'], ['BAD', $products[0]->sku], ['BAD', 'MISSING']],
        ]);
        $disk = 'product-group-imports/bad.xlsx';
        Storage::disk('local')->put($disk, file_get_contents($path));
        @unlink($path);
        $import = ProductImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'bad.xlsx', 'disk' => 'local', 'path' => $disk, 'status' => 'pending', 'operation' => 'group']);
        $this->app->call([new ProcessProductImport($import->id), 'handle']);
        $this->assertSame('completed', $import->fresh()->status);
        $this->assertSame(1, $import->fresh()->failed_rows);
        $this->assertSame(2, $import->fresh()->total_rows);
        $this->assertSame(2, $import->fresh()->processed_rows);
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

    /** @return list<string> */
    private function groupHeaders(bool $withIndividuals = true): array
    {
        return [
            'Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название',
            ...array_map(fn (int $number): string => 'Ось '.$number, range(1, 20)),
            ...($withIndividuals ? array_map(fn (int $number): string => 'Индивидуальная '.$number, range(1, 20)) : []),
        ];
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
