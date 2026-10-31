<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductCollectionImportResolver;
use App\Services\ProductImportService;
use App\Services\ProductImportTemplateService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCollectionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_distinguishes_missing_blank_alias_and_conflict(): void
    {
        [$category, $brand, $collection, $product] = $this->catalog();
        $resolver = app(ProductCollectionImportResolver::class);
        $this->assertSame([], $resolver->resolve([], $category, $brand, $product, 2));
        $this->assertSame(['collection_id' => null], $resolver->resolve(['collection_name' => ''], $category, $brand, $product, 2));
        $this->assertSame(['collection_id' => $collection->id], $resolver->resolve(['attribute.kollektsiya' => $collection->name], $category, $brand, $product, 2));

        $this->expectException(ValidationException::class);
        $resolver->resolve(['collection_name' => $collection->name, 'attribute.kollektsiya' => 'Другая'], $category, $brand, $product, 2);
    }

    public function test_import_uses_canonical_collection_and_leaves_archived_value_untouched(): void
    {
        [$category, $brand, $collection, $product] = $this->catalog();
        $attribute = Attribute::factory()->create(['name' => 'Коллекция', 'slug' => 'kollektsiya', 'type' => 'string']);
        $category->attributes()->attach($attribute, ['is_required' => true]);
        $product->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => 'Архив']);
        $rows = app(ProductImportTemplateService::class)->editingRows($category);
        $this->assertSame($collection->name, $rows[0]['collection_name']);
        $this->assertArrayNotHasKey('attribute.kollektsiya', $rows[0]);
        $row = $rows[0];
        $row['collection_name'] = '';
        app(ProductImportService::class)->createTemplateRow($this->actor(), $category, $row, 2, true);
        $this->assertNull($product->refresh()->collection_id);
        $this->assertSame('Архив', $product->attributeValues()->sole()->value);
    }

    public function test_unknown_collection_or_wrong_brand_rejects_without_creating_records(): void
    {
        [$category, $brand, , $product] = $this->catalog();
        $resolver = app(ProductCollectionImportResolver::class);
        foreach ([$brand, null, Brand::factory()->create()] as $candidate) {
            try {
                $resolver->resolve(['collection_name' => 'Неизвестная'], $category, $candidate, $product, 2);
                $this->fail('Invalid collection must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('Строка 2:', $exception->errors()['file'][0]);
            }
        }
        $this->assertDatabaseCount('product_collections', 1);
    }

    /** @return array{Category, Brand, ProductCollection, Product} */
    private function catalog(): array
    {
        $category = Category::factory()->create(['uses_collections' => true]);
        $brand = Brand::factory()->create();
        $collection = ProductCollection::query()->create(['brand_id' => $brand->id, 'name' => 'Аллея', 'slug' => 'alleya', 'is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'collection_id' => $collection->id, 'is_active' => false]);

        return [$category, $brand, $collection, $product];
    }

    private function actor(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());

        return $user;
    }
}
