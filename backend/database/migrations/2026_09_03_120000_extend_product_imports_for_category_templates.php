<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imports', function (Blueprint $table): void {
            // Keep the category ID even if it is deleted: this must never become a legacy import.
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('last_processed_row')->default(0);
        });
        Schema::create('product_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('name')->nullable();
            $table->json('messages');
            $table->json('values');
            $table->unique(['product_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_errors');
        Schema::table('product_imports', fn (Blueprint $table) => $table->dropColumn(['category_id', 'total_rows', 'failed_rows', 'last_processed_row']));
    }
};
