<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing pages keep their previous indexable behavior.
        Schema::table('pages', function (Blueprint $table): void {
            $table->boolean('is_indexable')->default(true);
        });
        // Future pages require an explicit choice to enable indexing.
        Schema::table('pages', function (Blueprint $table): void {
            $table->boolean('is_indexable')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('is_indexable');
        });
    }
};
