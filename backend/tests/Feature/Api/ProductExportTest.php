<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ProductExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_manager_can_export_the_filtered_sorted_catalog_with_round_trip_values(): void
    {
        $actor = $this->userWithPermission('imports.manage');
        $category = Category::factory()->create(['name' => 'Керамогранит', 'slug' => 'porcelain']);
        $color = Attribute::factory()->create(['name' => 'Цвет', 'slug' => 'color', 'type' => 'select', 'sort_order' => 1]);
        $features = Attribute::factory()->create(['name' => 'Особенности', 'slug' => 'features', 'type' => 'multiselect', 'sort_order' => 2]);
        $rectified = Attribute::factory()->create(['name' => 'Ректифицированный', 'slug' => 'rectified', 'type' => 'boolean', 'sort_order' => 3]);

        $second = Product::factory()->create([
            'category_id' => $category->id,
            'name' => '=SUM(1,1)',
            'slug' => 'matching-second',
            'sku' => '02000002',
            'barcode' => '0012345678901',
            'is_active' => true,
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

        $response = $this->actingAs($actor)->get('/api/v1/admin/products/export?is_active=1&sort=sku&direction=asc')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertMatchesRegularExpression('/attachment; filename=products-\d{4}-\d{2}-\d{2}-\d{6}\.xlsx/', $disposition);

        $rows = $this->readRows($response->baseResponse);
        $headers = $rows[0];
        $this->assertSame([$first->id, $second->id], [$rows[1][0], $rows[2][0]]);
        $this->assertSame('01000001', $rows[1][array_search('sku', $headers, true)]);
        $this->assertSame('0012345678901', $rows[2][array_search('barcode', $headers, true)]);
        $this->assertSame('=SUM(1,1)', $rows[2][array_search('name', $headers, true)]);
        $this->assertSame('white', $rows[1][array_search('attribute.color', $headers, true)]);
        $this->assertSame('["matte","frost-resistant"]', $rows[1][array_search('attribute.features', $headers, true)]);
        $this->assertTrue($rows[1][array_search('attribute.rectified', $headers, true)]);
        $this->assertSame('', $rows[2][array_search('attribute.color', $headers, true)]);
    }

    public function test_empty_export_still_contains_the_stable_header_row(): void
    {
        $response = $this->actingAs($this->userWithPermission('imports.manage'))
            ->get('/api/v1/admin/products/export?search=missing-product')
            ->assertOk();

        $rows = $this->readRows($response->baseResponse);

        $this->assertCount(1, $rows);
        $this->assertSame(['id', 'sku', 'article_number', 'barcode', 'name'], array_slice($rows[0], 0, 5));
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
    private function readRows(BinaryFileResponse $response): array
    {
        $reader = new Reader;
        $reader->open($response->getFile()->getPathname());
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
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
