<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('article_number', 100)->nullable()->unique();
            $table->string('barcode', 14)->nullable()->unique();
            $table->enum('unit', ['piece', 'square_meter', 'linear_meter', 'package', 'kilogram', 'liter', 'set'])
                ->default('piece');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique(['article_number']);
            $table->dropUnique(['barcode']);
            $table->dropColumn(['article_number', 'barcode', 'unit']);
        });
    }
};
