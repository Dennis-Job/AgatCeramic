<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Nullable at database level until every legacy product/variant has been converted.
            $table->string('sku')->nullable()->unique();
            $table->string('article_number', 100)->nullable()->unique();
            $table->string('barcode', 14)->nullable()->unique();
            $table->enum('unit', ['piece', 'square_meter', 'linear_meter', 'package', 'kilogram', 'liter', 'set'])->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->nullable();
        });

        Schema::table('category_attribute', function (Blueprint $table): void {
            $table->boolean('is_required')->default(false);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->foreignId('standalone_product_id')->nullable()->unique()->constrained('products')->nullOnDelete();
        });
        DB::table('category_attribute')->update([
            'is_required' => DB::raw('(SELECT attributes.is_required FROM attributes WHERE attributes.id = category_attribute.attribute_id)'),
        ]);

        Schema::create('product_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->timestamps();
        });
        Schema::create('product_group_axes', function (Blueprint $table): void {
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['product_group_id', 'attribute_id']);
        });
        Schema::create('product_group_members', function (Blueprint $table): void {
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['product_group_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_group_members');
        Schema::dropIfExists('product_group_axes');
        Schema::dropIfExists('product_groups');
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('standalone_product_id');
        });
        Schema::table('category_attribute', fn (Blueprint $table) => $table->dropColumn('is_required'));
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['sku']);
            $table->dropUnique(['article_number']);
            $table->dropUnique(['barcode']);
            $table->dropColumn(['sku', 'article_number', 'barcode', 'unit', 'price', 'old_price', 'stock_quantity']);
        });
    }
};
