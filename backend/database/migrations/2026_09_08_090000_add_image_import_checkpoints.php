<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_image_imports', function (Blueprint $table): void {
            $table->json('processed_skus')->nullable()->after('failed_folders');
        });
    }

    public function down(): void
    {
        Schema::table('product_image_imports', function (Blueprint $table): void {
            $table->dropColumn('processed_skus');
        });
    }
};
