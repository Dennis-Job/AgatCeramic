<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('row_number');
            $table->string('name')->nullable();
            $table->json('payload');
            $table->json('attribute_payload');
            $table->string('status', 16)->default('pending')->index();
            $table->timestampsTz();
            $table->unique(['product_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_items');
    }
};
