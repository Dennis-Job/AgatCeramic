<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $primaryProductIds = [];

        foreach (DB::table('product_images')->where('is_primary', true)->orderBy('product_id')->orderBy('sort_order')->orderBy('id')->get(['id', 'product_id']) as $image) {
            $values = get_object_vars($image);
            $imageId = $values['id'] ?? null;
            $productId = $values['product_id'] ?? null;
            if ((! is_int($imageId) && ! is_string($imageId)) || (! is_int($productId) && ! is_string($productId))) {
                throw new RuntimeException('Product image identifiers must be scalar values.');
            }

            if (isset($primaryProductIds[$productId])) {
                DB::table('product_images')->where('id', $imageId)->update(['is_primary' => false]);

                continue;
            }

            $primaryProductIds[$productId] = true;
        }

        $predicate = DB::getDriverName() === 'pgsql' ? 'is_primary' : 'is_primary = 1';
        DB::statement("CREATE UNIQUE INDEX product_images_one_primary_per_product ON product_images (product_id) WHERE {$predicate}");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS product_images_one_primary_per_product');
    }
};
