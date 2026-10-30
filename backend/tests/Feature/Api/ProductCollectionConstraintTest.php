<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCollectionConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL enforces composite collection/brand and normalized Unicode constraints.');
        }
    }

    public function test_database_rejects_a_collection_from_a_different_brand(): void
    {
        $collection = ProductCollection::factory()->create();
        $product = Product::factory()->create(['brand_id' => Brand::factory()->create()->id]);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('products')->where('id', $product->id)->update(['collection_id' => $collection->id]));
    }

    public function test_database_rejects_nonnull_collection_without_brand(): void
    {
        $collection = ProductCollection::factory()->create();
        $product = Product::factory()->create(['brand_id' => null]);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('products')->where('id', $product->id)->update(['collection_id' => $collection->id]));
    }

    public function test_database_normalizes_collection_names_within_each_brand(): void
    {
        $collection = ProductCollection::factory()->create(['name' => 'Аллея']);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => ProductCollection::factory()->create(['brand_id' => $collection->brand_id, 'name' => ' АЛЛЕЯ ', 'slug' => 'other']));
    }
}
