<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_counters', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->unsignedInteger('next_value');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->char('sku_prefix', 2)->nullable()->index();
        });

        $categories = [];
        foreach (DB::table('categories')->orderBy('id')->get(['id', 'parent_id']) as $row) {
            $categories[] = $this->category($row);
        }
        $roots = array_values(array_filter($categories, static fn (array $category): bool => $category['parent_id'] === null));
        if (count($roots) > 99) {
            throw new RuntimeException('Automatic SKU numbering supports at most 99 root categories.');
        }

        $rootByCategory = [];
        $byId = [];
        foreach ($categories as $category) {
            $byId[$category['id']] = $category;
        }
        foreach ($categories as $category) {
            $current = $category;
            $visited = [];
            while ($current['parent_id'] !== null) {
                $parentId = $current['parent_id'];
                if (isset($visited[$current['id']]) || ! isset($byId[$parentId])) {
                    throw new RuntimeException('Cannot assign SKU prefixes to an invalid category tree.');
                }
                $visited[$current['id']] = true;
                $current = $byId[$parentId];
            }
            $rootByCategory[$category['id']] = $current['id'];
        }

        foreach ($roots as $index => $root) {
            $categoryIds = array_keys(array_filter($rootByCategory, static fn (int $rootId): bool => $rootId === $root['id']));
            DB::table('categories')->whereIn('id', $categoryIds)->update([
                'sku_prefix' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            ]);
        }

        $largestExistingNumber = 0;
        foreach (DB::table('products')->pluck('sku') as $sku) {
            if (is_string($sku) && preg_match('/^\d{8}$/', $sku) === 1) {
                $largestExistingNumber = max($largestExistingNumber, (int) substr($sku, 2));
            }
        }

        DB::table('catalog_counters')->insert([
            ['name' => 'category_sku_prefix', 'next_value' => count($roots) + 1],
            ['name' => 'product_sku_number', 'next_value' => $largestExistingNumber + 1],
        ]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('sku_prefix');
        });
        Schema::dropIfExists('catalog_counters');
    }

    /** @return array{id: int, parent_id: int|null} */
    private function category(object $row): array
    {
        $values = get_object_vars($row);
        $id = $values['id'] ?? null;
        $parentId = $values['parent_id'] ?? null;

        if (! is_int($id) || ($parentId !== null && ! is_int($parentId))) {
            throw new RuntimeException('Category identifiers must be integers.');
        }

        return ['id' => $id, 'parent_id' => $parentId];
    }
};
