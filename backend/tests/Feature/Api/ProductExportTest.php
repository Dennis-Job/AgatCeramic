<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductImportService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ProductExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_manager_can_export_the_filtered_sorted_catalog_with_readable_values(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['name' => 'Керамогранит', 'slug' => 'porcelain']);
        $color = Attribute::factory()->create(['name' => 'Цвет', 'slug' => 'color', 'type' => 'select', 'sort_order' => 1]);
        $features = Attribute::factory()->create(['name' => 'Особенности', 'slug' => 'features', 'type' => 'multiselect', 'sort_order' => 2]);
        $rectified = Attribute::factory()->create(['name' => 'Ректифицированный', 'slug' => 'rectified', 'type' => 'boolean', 'sort_order' => 3]);
        $weight = Attribute::factory()->create(['name' => 'Вес', 'slug' => 'weight', 'type' => 'decimal', 'unit' => 'кг', 'sort_order' => 4]);
        $color->options()->create(['value' => 'white', 'label' => 'Белый']);
        $features->options()->createMany([['value' => 'matte', 'label' => 'Матовый'], ['value' => 'frost-resistant', 'label' => 'Морозостойкий']]);

        $second = Product::factory()->create([
            'category_id' => $category->id,
            'name' => '=SUM(1,1)',
            'slug' => 'matching-second',
            'sku' => '02000002',
            'barcode' => '0012345678901',
            'is_active' => true,
            'unit' => 'square_meter',
            'price' => '1200.50',
            'is_on_sale' => false,
        ]);
        $first = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Совпадающий первый',
            'slug' => 'matching-first',
            'sku' => '01000001',
            'is_active' => true,
        ]);
        Product::factory()->create(['category_id' => $category->id, 'slug' => 'excluded', 'sku' => '03000003', 'is_active' => false]);

        ProductAttributeValue::query()->create(['product_id' => $first->id, 'attribute_id' => $color->id, 'value' => 'white']);
        ProductAttributeValue::query()->create(['product_id' => $first->id, 'attribute_id' => $features->id, 'value' => ['matte', 'frost-resistant']]);
        ProductAttributeValue::query()->create(['product_id' => $first->id, 'attribute_id' => $rectified->id, 'value' => true]);
        ProductAttributeValue::query()->create(['product_id' => $second->id, 'attribute_id' => $rectified->id, 'value' => false]);
        ProductAttributeValue::query()->create(['product_id' => $first->id, 'attribute_id' => $weight->id, 'value' => 12.5]);

        $response = $this->actingAs($actor)->get('/api/v1/admin/products/export?is_active=1&sort=sku&direction=asc')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertMatchesRegularExpression('/attachment; filename=products-\d{4}-\d{2}-\d{2}-\d{6}\.xlsx/', $disposition);

        $sheets = $this->readSheets($response->baseResponse);
        $rows = $sheets['Товары'];
        $headers = $rows[0];
        $this->assertSame(['01000001', '02000002'], [$rows[1][0], $rows[2][0]]);
        $firstRow = array_combine($headers, $rows[1]);
        $secondRow = array_combine($headers, $rows[2]);
        $this->assertSame('0012345678901', $secondRow['Штрихкод']);
        $this->assertSame('=SUM(1,1)', $secondRow['Название']);
        $this->assertSame('Белый', $firstRow['Цвет']);
        $this->assertSame('Матовый; Морозостойкий', $firstRow['Особенности']);
        $this->assertSame('Да', $firstRow['Ректифицированный']);
        $this->assertSame('Нет', $secondRow['Ректифицированный']);
        $this->assertSame('Да', $secondRow['Активность']);
        $this->assertSame('Нет', $secondRow['Распродажа']);
        $this->assertSame('Квадратный метр', $secondRow['Единица продажи']);
        $this->assertSame(1200.5, $secondRow['Цена']);
        $this->assertInstanceOf(\DateTimeInterface::class, $secondRow['Создан']);
        $this->assertInstanceOf(\DateTimeInterface::class, $secondRow['Изменён']);
        $this->assertSame(12.5, $firstRow['Вес (кг)']);
        $this->assertSame('', $secondRow['Цвет']);
        $this->assertSame(['Товары', 'SEO товаров', 'SEO характеристик'], array_keys($sheets));
        $this->assertSame(['01000001', $first->name, 'matching-first', 'Керамогранит', 'porcelain', $first->brand->name, $first->brand->slug], $sheets['SEO товаров'][1]);
        $this->assertContains(['Вес', 'кг', 'weight'], $sheets['SEO характеристик']);
        foreach ($headers as $header) {
            $this->assertDoesNotMatchRegularExpression('/(^id$|_id|slug|attribute\.)/', $header);
        }
        $zip = new \ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('state="frozen"', $xml);
        $this->assertStringContainsString('<autoFilter ref="A1:U3"', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
        $zip->close();
    }

    public function test_export_appends_all_public_image_urls_in_gallery_order_and_import_preserves_images(): void
    {
        config(['filesystems.disks.public.url' => 'https://catalog.example/storage']);
        $actor = $this->userWithPermission('imports.manage');
        $products = collect();
        foreach (['Gallery first', 'Gallery second', 'Gallery empty', 'Excluded'] as $index => $name) {
            $products->push(Product::factory()->create(['name' => $name, 'sku' => '800000'.($index + 1), 'is_active' => false]));
        }
        foreach ([0 => [[false, 2], [true, 99], [false, 1]], 1 => [[true, 0]], 3 => [[true, 0], [false, 1], [false, 2], [false, 3]]] as $index => $images) {
            foreach ($images as $number => [$primary, $sort]) {
                $products[$index]->images()->create([
                    'disk' => 'public', 'path' => 'products/'.$products[$index]->sku.'/image-'.($number + 1).'.jpg',
                    'mime_type' => 'image/jpeg', 'size' => 100, 'is_primary' => $primary, 'sort_order' => $sort,
                ]);
            }
        }
        Attribute::factory()->create(['name' => 'Вес', 'unit' => 'кг']);
        $response = $this->actingAs($actor)->get('/api/v1/admin/products/export?search=Gallery&sort=sku&direction=asc')->assertOk();
        $rows = $this->readSheets($response->baseResponse)['Товары'];
        $headers = $rows[0];
        $this->assertSame(['Вес (кг)', 'Изображение 1', 'Изображение 2', 'Изображение 3'], array_slice($headers, -4));
        $this->assertNotContains('Основное изображение', $headers);
        $this->assertNotContains('Изображение 4', $headers);
        $prefix = 'https://catalog.example/storage/products/8000001/';
        $this->assertSame([$prefix.'image-2.jpg', $prefix.'image-3.jpg', $prefix.'image-1.jpg'], array_slice($rows[1], -3));
        $this->assertSame(['https://catalog.example/storage/products/8000002/image-1.jpg', '', ''], array_slice($rows[2], -3));
        $this->assertSame(['', '', ''], array_slice($rows[3], -3));
        foreach (array_slice($rows, 1) as $row) {
            $this->assertCount(count($headers), $row);
        }
        $before = $products[0]->images()->orderBy('id')->get()->toArray();
        $result = app(ProductImportService::class)->import($actor, $response->baseResponse->getFile()->getPathname());
        $this->assertSame(['created' => 0, 'updated' => 3, 'processed' => 3], $result);
        $this->assertSame($before, $products[0]->images()->orderBy('id')->get()->toArray());
    }

    public function test_empty_export_still_contains_the_stable_header_row(): void
    {
        $response = $this->actingAs($this->userWithPermission('imports.manage'))
            ->get('/api/v1/admin/products/export?search=missing-product')
            ->assertOk();

        $rows = $this->readSheets($response->baseResponse)['Товары'];

        $this->assertCount(1, $rows);
        $this->assertSame(['SKU', 'Артикул', 'Штрихкод', 'Название', 'Описание'], array_slice($rows[0], 0, 5));
    }

    public function test_selected_skus_restrict_both_product_sheets_and_image_columns_with_existing_filters_and_sort(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $first = Product::factory()->create(['sku' => '0001', 'is_active' => true]);
        Product::factory()->create(['sku' => '0002', 'is_active' => true]);
        Product::factory()->create(['sku' => '0003', 'is_active' => false]);
        $excluded = Product::factory()->create(['sku' => '9999', 'is_active' => true]);
        foreach ([[$first, 1], [$excluded, 3]] as [$product, $count]) {
            for ($index = 1; $index <= $count; $index++) {
                $product->images()->create([
                    'disk' => 'public', 'path' => 'products/'.$product->sku.'/'.$index.'.jpg',
                    'mime_type' => 'image/jpeg', 'size' => 100, 'is_primary' => $index === 1, 'sort_order' => $index,
                ]);
            }
        }

        $response = $this->actingAs($actor)
            ->postJson('/api/v1/admin/products/export', ['skus' => ['0002', '0001', '0003'], 'is_active' => true, 'sort' => 'sku', 'direction' => 'asc'])
            ->assertOk();

        $sheets = $this->readSheets($response->baseResponse);
        $this->assertSame(['0001', '0002'], array_column(array_slice($sheets['Товары'], 1), 0));
        $this->assertSame(['0001', '0002'], array_column(array_slice($sheets['SEO товаров'], 1), 0));
        $this->assertContains('Изображение 1', $sheets['Товары'][0]);
        $this->assertNotContains('Изображение 2', $sheets['Товары'][0]);
    }

    public function test_unknown_sku_prevents_download_even_when_other_skus_exist(): void
    {
        Product::factory()->create(['sku' => '0001']);

        $response = $this->actingAs($this->userWithPermission('imports.manage'))
            ->postJson('/api/v1/admin/products/export', ['skus' => ['0001', 'unknown', '0009']])
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json');

        $this->assertSame(['Товар с SKU unknown не найден.'], $response->json('error.details')['skus.1']);
        $this->assertSame(['Товар с SKU 0009 не найден.'], $response->json('error.details')['skus.2']);
    }

    public function test_invalid_sku_lists_never_fall_back_to_the_full_catalog(): void
    {
        $this->actingAs($this->userWithPermission('imports.manage'));
        Product::factory()->create(['sku' => '0001']);

        foreach ([[], null, '', '0001', array_fill(0, 1001, '0001')] as $skus) {
            $this->postJson('/api/v1/admin/products/export', ['skus' => $skus])
                ->assertUnprocessable()
                ->assertJsonStructure(['error' => ['details' => ['skus']]]);
        }

        foreach ([[''], [str_repeat('x', 256)], [['0001']], [1]] as $skus) {
            $this->postJson('/api/v1/admin/products/export', ['skus' => $skus])
                ->assertUnprocessable()
                ->assertJsonStructure(['error' => ['details' => ['skus.0']]]);
        }
    }

    public function test_selected_export_requires_skus_and_get_rejects_sku_selection(): void
    {
        $this->actingAs($this->userWithPermission('imports.manage'));

        $this->postJson('/api/v1/admin/products/export', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['skus']]]);
        $this->getJson('/api/v1/admin/products/export?skus[]=0001')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['skus']]]);
    }

    public function test_selected_export_accepts_the_maximum_sku_count_and_exports_each_product_once(): void
    {
        Product::factory()->create(['sku' => '0001']);
        $response = $this->actingAs($this->userWithPermission('imports.manage'))
            ->postJson('/api/v1/admin/products/export', ['skus' => array_fill(0, 1000, '0001')])
            ->assertOk();

        $this->assertSame(['0001'], array_column(array_slice($this->readSheets($response->baseResponse)['Товары'], 1), 0));
    }

    public function test_selected_export_requires_the_dedicated_import_permission(): void
    {
        Product::factory()->create(['sku' => '0001']);
        $this->postJson('/api/v1/admin/products/export', ['skus' => ['0001']])->assertUnauthorized();
        $this->actingAs($this->userWithPermission('catalog.manage'))
            ->postJson('/api/v1/admin/products/export', ['skus' => ['0001']])->assertForbidden();
        foreach ([['unknown'], [], '0001'] as $skus) {
            $this->postJson('/api/v1/admin/products/export', ['skus' => $skus])->assertForbidden();
        }
    }

    public function test_export_requires_the_dedicated_import_permission(): void
    {
        $this->getJson('/api/v1/admin/products/export')->assertUnauthorized();

        $this->actingAs($this->userWithPermission('catalog.manage'))
            ->get('/api/v1/admin/products/export')
            ->assertForbidden();

        $this->actingAs($this->userWithPermission('imports.manage'))
            ->get('/api/v1/admin/products/export')
            ->assertOk();
    }

    public function test_export_validates_filters_and_rejects_pagination(): void
    {
        $actor = $this->userWithPermission('imports.manage');

        $this->actingAs($actor)->getJson('/api/v1/admin/products/export?sort=price&has_stock=invalid&per_page=10')
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['sort', 'has_stock', 'per_page']]]);
    }

    /** @return list<list<bool|float|int|string|null>> */
    private function readSheets(BinaryFileResponse $response): array
    {
        $reader = new Reader;
        $reader->open($response->getFile()->getPathname());
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows[$sheet->getName()] = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();

        return $rows;
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
