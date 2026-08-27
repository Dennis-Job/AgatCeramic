<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            return;
        }

        $remaining = DB::table('product_variants')->count();
        if ($remaining > 0) {
            throw new RuntimeException("Refusing legacy table cleanup: {$remaining} product variants remain. Run and verify catalog:migrate-standalone-products --apply --finalize first.");
        }

        Schema::dropIfExists('product_variant_attribute_values');
        Schema::drop('product_variants');
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('sku')->unique();
                $table->string('article_number', 100)->nullable()->unique();
                $table->string('barcode', 14)->nullable()->unique();
                $table->enum('unit', ['piece', 'square_meter', 'linear_meter', 'package', 'kilogram', 'liter', 'set'])->default('piece');
                $table->decimal('price', 12, 2);
                $table->decimal('old_price', 12, 2)->nullable();
                $table->unsignedInteger('stock_quantity')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->foreignId('standalone_product_id')->nullable()->unique()->constrained('products')->nullOnDelete();
                $table->timestamps();
                $table->index(['product_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('product_variant_attribute_values')) {
            Schema::create('product_variant_attribute_values', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
                $table->json('value');
                $table->timestamps();
                $table->unique(['product_variant_id', 'attribute_id']);
                $table->index('attribute_id');
            });
        }
    }
};
