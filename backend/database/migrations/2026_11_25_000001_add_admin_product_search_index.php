<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(<<<'SQL'
            CREATE INDEX products_admin_search_trgm_index ON products USING gin (
                LOWER(name) gin_trgm_ops,
                LOWER(slug) gin_trgm_ops,
                LOWER(sku) gin_trgm_ops,
                LOWER(article_number) gin_trgm_ops,
                barcode gin_trgm_ops
            )
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS products_admin_search_trgm_index');
        }
    }
};
