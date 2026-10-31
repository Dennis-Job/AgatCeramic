<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('image_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['brand_id', 'slug']);
            $table->unique(['id', 'brand_id']);
            $table->index(['brand_id', 'is_active', 'name']);
        });
        DB::statement('CREATE UNIQUE INDEX product_collections_brand_name_unique ON product_collections (brand_id, LOWER(TRIM(name)))');
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('uses_collections')->default(false);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('collection_id')->nullable()->constrained('product_collections')->restrictOnDelete();
            $table->index(['collection_id', 'brand_id']);
            $table->foreign(['collection_id', 'brand_id'], 'products_collection_brand_foreign')
                ->references(['id', 'brand_id'])->on('product_collections')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_collection_requires_brand CHECK (collection_id IS NULL OR brand_id IS NOT NULL)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products DROP CONSTRAINT products_collection_requires_brand');
        }
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign('products_collection_brand_foreign');
            $table->dropForeign(['collection_id']);
            $table->dropIndex(['collection_id', 'brand_id']);
            $table->dropColumn('collection_id');
        });
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('uses_collections'));
        Schema::dropIfExists('product_collections');
    }
};
