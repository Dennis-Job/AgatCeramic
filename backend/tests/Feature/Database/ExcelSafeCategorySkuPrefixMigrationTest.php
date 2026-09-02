<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExcelSafeCategorySkuPrefixMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_category_prefixes_without_rewriting_products_or_images(): void
    {
        $first = Category::factory()->create(['sku_prefix' => '01']);
        $child = Category::factory()->create(['parent_id' => $first->id, 'sku_prefix' => '01']);
        foreach (['10', '11', '19', '90'] as $prefix) {
            Category::factory()->create(['sku_prefix' => $prefix]);
        }
        $product = Product::factory()->create(['category_id' => $child->id, 'sku' => '01000001']);
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => "product-images/{$product->id}/01000001_2.jpg",
            'mime_type' => 'image/jpeg',
            'size' => 10,
            'alt' => '01000001_2',
            'is_primary' => true,
        ]);
        DB::table('catalog_counters')->where('name', 'category_sku_prefix')->update(['next_value' => 91]);

        $migration = require database_path('migrations/2026_09_02_130000_use_excel_safe_category_sku_prefixes.php');
        $migration->up();

        $this->assertSame(['1', '1', '11', '12', '21', '99'], Category::query()->orderBy('id')->pluck('sku_prefix')->all());
        $this->assertSame('01000001', $product->fresh()->sku);
        $this->assertSame("product-images/{$product->id}/01000001_2.jpg", $image->fresh()->path);
        $this->assertSame('01000001_2', $image->fresh()->alt);
        $this->assertSame(91, (int) DB::table('catalog_counters')->where('name', 'category_sku_prefix')->value('next_value'));
    }
}
