<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [];
        foreach (DB::table('categories')->get(['id', 'parent_id', 'sku_prefix']) as $row) {
            $categories[] = $this->category($row);
        }
        $byId = [];
        foreach ($categories as $category) {
            $byId[$category['id']] = $category;
        }
        $rootPrefixes = [];

        foreach ($categories as $category) {
            $prefix = $category['sku_prefix'];
            if ($prefix === null
                || preg_match('/^\d{2}$/', $prefix) !== 1
                || (int) $prefix < 1
                || (int) $prefix > 90) {
                throw new RuntimeException('Existing category SKU prefixes cannot be converted to the Excel-safe 1-99 range.');
            }

            $current = $category;
            $visited = [];
            while ($current['parent_id'] !== null) {
                $parentId = $current['parent_id'];
                if (isset($visited[$current['id']]) || ! isset($byId[$parentId])) {
                    throw new RuntimeException('Cannot convert SKU prefixes for an invalid category tree.');
                }
                $visited[$current['id']] = true;
                $current = $byId[$parentId];
            }

            if ($current['sku_prefix'] !== $prefix) {
                throw new RuntimeException('A category subtree has inconsistent SKU prefixes.');
            }
            if (isset($rootPrefixes[$current['sku_prefix']]) && $rootPrefixes[$current['sku_prefix']] !== $current['id']) {
                throw new RuntimeException('Two root category trees share the same SKU prefix.');
            }
            $rootPrefixes[$current['sku_prefix']] = $current['id'];
        }

        $counter = DB::table('catalog_counters')->where('name', 'category_sku_prefix')->first();
        $counterValues = $counter === null ? [] : get_object_vars($counter);
        $nextValue = $counterValues['next_value'] ?? null;
        if (! is_int($nextValue) || $nextValue > 91) {
            throw new RuntimeException('More than 90 category SKU prefixes have already been allocated.');
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('categories', function (Blueprint $table): void {
                $table->string('sku_prefix', 2)->nullable()->change();
            });
        }

        DB::transaction(function () use ($categories): void {
            foreach ($categories as $category) {
                $ordinal = (int) $category['sku_prefix'];
                DB::table('categories')->where('id', $category['id'])->update([
                    'sku_prefix' => (string) ($ordinal + intdiv($ordinal - 1, 9)),
                ]);
            }
        });
    }

    public function down(): void
    {
        $categories = [];
        foreach (DB::table('categories')->whereNotNull('sku_prefix')->get(['id', 'sku_prefix']) as $row) {
            $categories[] = $this->category($row);
        }

        DB::transaction(function () use ($categories): void {
            foreach ($categories as $category) {
                $prefix = (int) $category['sku_prefix'];
                if ($prefix < 1 || $prefix > 99 || $prefix % 10 === 0) {
                    throw new RuntimeException('Existing category SKU prefixes cannot be restored to the legacy format.');
                }

                $ordinal = $prefix - intdiv($prefix, 10);
                DB::table('categories')->where('id', $category['id'])->update([
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

    /** @return array{id: int, parent_id: int|null, sku_prefix: string|null} */
    private function category(object $row): array
    {
        $values = get_object_vars($row);
        $id = $values['id'] ?? null;
        $parentId = $values['parent_id'] ?? null;
        $prefix = $values['sku_prefix'] ?? null;

        if (! is_int($id) || ($parentId !== null && ! is_int($parentId)) || ($prefix !== null && ! is_string($prefix))) {
            throw new RuntimeException('Category migration values have invalid types.');
        }

        return ['id' => $id, 'parent_id' => $parentId, 'sku_prefix' => $prefix];
    }
};
