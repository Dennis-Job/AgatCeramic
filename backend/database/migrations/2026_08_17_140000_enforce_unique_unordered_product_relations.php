<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $functions = DB::getDriverName() === 'pgsql'
            ? ['LEAST', 'GREATEST']
            : ['MIN', 'MAX'];

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX product_relations_unique_unordered_pair ON product_relations (%s(product_id, related_product_id), %s(product_id, related_product_id))',
            ...$functions,
        ));
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS product_relations_unique_unordered_pair');
    }
};
