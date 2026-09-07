<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imports', function (Blueprint $table): void {
            $table->string('operation', 32)->default('catalog')->index();
        });
    }

    public function down(): void
    {
        Schema::table('product_imports', fn (Blueprint $table) => $table->dropColumn('operation'));
    }
};
