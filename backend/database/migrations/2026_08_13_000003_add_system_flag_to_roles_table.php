<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->index()->after('description');
        });

        DB::table('roles')
            ->whereIn('slug', ['super-admin', 'administrator', 'catalog-manager', 'order-manager', 'content-manager', 'seo-manager', 'analyst'])
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex(['is_system']);
            $table->dropColumn('is_system');
        });
    }
};
