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

        $categories = DB::table('categories')->orderBy('id')->get(['id', 'parent_id']);
        $roots = $categories->whereNull('parent_id')->values();
        if ($roots->count() > 99) {
            throw new RuntimeException('Automatic SKU numbering supports at most 99 root categories.');
        }

        $rootByCategory = [];
        $byId = $categories->keyBy('id');
        foreach ($categories as $category) {
            $current = $category;
            $visited = [];
            while ($current->parent_id !== null) {
                if (isset($visited[$current->id]) || ! $byId->has($current->parent_id)) {
                    throw new RuntimeException('Cannot assign SKU prefixes to an invalid category tree.');
                }
                $visited[$current->id] = true;
                $current = $byId->get($current->parent_id);
            }
            $rootByCategory[$category->id] = $current->id;
        }

        foreach ($roots as $index => $root) {
            $categoryIds = array_keys(array_filter($rootByCategory, fn (int $rootId): bool => $rootId === $root->id));
            DB::table('categories')->whereIn('id', $categoryIds)->update([
                'sku_prefix' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            ]);
        }

        $largestExistingNumber = DB::table('products')->pluck('sku')
            ->filter(fn (?string $sku): bool => $sku !== null && preg_match('/^\d{8}$/', $sku) === 1)
            ->map(fn (string $sku): int => (int) substr($sku, 2))
            ->max() ?? 0;

        DB::table('catalog_counters')->insert([
            ['name' => 'category_sku_prefix', 'next_value' => $roots->count() + 1],
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
};
