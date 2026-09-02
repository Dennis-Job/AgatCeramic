<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogSkuService
{
    public function generate(Category $category): string
    {
        $prefix = $category->sku_prefix ?? $this->ensurePrefix($category);

        do {
            $number = $this->nextCounterValue('product_sku_number', 999999, 'The six-digit SKU number range is exhausted.');
            $sku = $prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    public function prefixForNewCategory(?Category $parent): string
    {
        return $parent === null
            ? $this->nextRootPrefix()
            : ($parent->sku_prefix ?? $this->ensurePrefix($parent));
    }

    public function reassignSubtree(Category $category, ?Category $parent): void
    {
        $prefix = $parent === null
            ? $this->nextRootPrefix()
            : ($parent->sku_prefix ?? $this->ensurePrefix($parent));

        $pending = [$category->id];
        while ($pending !== []) {
            DB::table('categories')->whereIn('id', $pending)->update(['sku_prefix' => $prefix]);
            $pending = DB::table('categories')->whereIn('parent_id', $pending)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }
        $category->sku_prefix = $prefix;
    }

    private function ensurePrefix(Category $category): string
    {
        $lineage = [];
        $current = $category;
        while (true) {
            $lineage[] = $current->id;
            if ($current->parent_id === null) {
                break;
            }
            $current = Category::query()->findOrFail($current->parent_id);
        }

        $root = Category::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();
        $prefix = $root->sku_prefix ?? $this->nextRootPrefix();
        DB::table('categories')->whereIn('id', $lineage)->update(['sku_prefix' => $prefix]);

        return $prefix;
    }

    private function nextRootPrefix(): string
    {
        $ordinal = $this->nextCounterValue(
            'category_sku_prefix',
            90,
            'The category type range is exhausted.',
        );

        return (string) ($ordinal + intdiv($ordinal - 1, 9));
    }

    private function nextCounterValue(string $name, int $maximum, string $message): int
    {
        $counter = DB::table('catalog_counters')->where('name', $name)->lockForUpdate()->first();
        if ($counter === null || $counter->next_value > $maximum) {
            throw ValidationException::withMessages(['category_id' => [$message]]);
        }

        DB::table('catalog_counters')->where('name', $name)->update(['next_value' => $counter->next_value + 1]);

        return (int) $counter->next_value;
    }
}
