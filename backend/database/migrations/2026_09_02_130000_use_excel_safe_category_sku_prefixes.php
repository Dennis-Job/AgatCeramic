<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $categories = DB::table('categories')->get(['id', 'parent_id', 'sku_prefix']);
        $byId = $categories->keyBy('id');
        $rootPrefixes = [];

        foreach ($categories as $category) {
            if ($category->sku_prefix === null
                || preg_match('/^\d{2}$/', (string) $category->sku_prefix) !== 1
                || (int) $category->sku_prefix < 1
                || (int) $category->sku_prefix > 90) {
                throw new RuntimeException('Existing category SKU prefixes cannot be converted to the Excel-safe 1-99 range.');
            }

            $current = $category;
            $visited = [];
            while ($current->parent_id !== null) {
                if (isset($visited[$current->id]) || ! $byId->has($current->parent_id)) {
                    throw new RuntimeException('Cannot convert SKU prefixes for an invalid category tree.');
                }
                $visited[$current->id] = true;
                $current = $byId->get($current->parent_id);
            }

            if ((string) $current->sku_prefix !== (string) $category->sku_prefix) {
                throw new RuntimeException('A category subtree has inconsistent SKU prefixes.');
            }
            if (isset($rootPrefixes[$current->sku_prefix]) && $rootPrefixes[$current->sku_prefix] !== $current->id) {
                throw new RuntimeException('Two root category trees share the same SKU prefix.');
            }
            $rootPrefixes[$current->sku_prefix] = $current->id;
        }

        $counter = DB::table('catalog_counters')->where('name', 'category_sku_prefix')->first();
        if ($counter === null || (int) $counter->next_value > 91) {
            throw new RuntimeException('More than 90 category SKU prefixes have already been allocated.');
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('categories', function (Blueprint $table): void {
                $table->string('sku_prefix', 2)->nullable()->change();
            });
        }

        DB::transaction(function () use ($categories): void {
            foreach ($categories as $category) {
                $ordinal = (int) $category->sku_prefix;
                DB::table('categories')->where('id', $category->id)->update([
                    'sku_prefix' => (string) ($ordinal + intdiv($ordinal - 1, 9)),
                ]);
            }
        });
    }

    public function down(): void
    {
        $categories = DB::table('categories')->whereNotNull('sku_prefix')->get(['id', 'sku_prefix']);

        DB::transaction(function () use ($categories): void {
            foreach ($categories as $category) {
                $prefix = (int) $category->sku_prefix;
                if ($prefix < 1 || $prefix > 99 || $prefix % 10 === 0) {
                    throw new RuntimeException('Existing category SKU prefixes cannot be restored to the legacy format.');
                }

                $ordinal = $prefix - intdiv($prefix, 10);
                DB::table('categories')->where('id', $category->id)->update([
                    'sku_prefix' => str_pad((string) $ordinal, 2, '0', STR_PAD_LEFT),
                ]);
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('categories', function (Blueprint $table): void {
                $table->char('sku_prefix', 2)->nullable()->change();
            });
        }
    }
};
