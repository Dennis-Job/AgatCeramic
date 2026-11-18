<?php

namespace App\Queries;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class PublicCatalogNavigationQuery
{
    public function __construct(private readonly PublicCatalogQuery $catalog) {}

    /**
     * @return array{brand: Brand|null, total: int, categories: list<array{category: Category, product_count: int}>}
     */
    public function forBrand(?string $slug): array
    {
        $hasBrand = $slug !== null && $slug !== '';
        $brand = $hasBrand ? Brand::query()->where('slug', $slug)->first() : null;
        if ($hasBrand && $brand === null) {
            return ['brand' => null, 'total' => 0, 'categories' => []];
        }

        // Inactive ancestors still connect public descendants to their active ancestors.
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get(['id', 'parent_id', 'name', 'slug', 'is_active'])->keyBy('id');
        $counts = $this->countsByCategory($categories, $brand);
        $navigation = [];
        foreach ($categories as $category) {
            $count = $counts['categories'][$category->id] ?? 0;
            if ($category->is_active && ($brand === null || $count > 0)) {
                $navigation[] = ['category' => $category, 'product_count' => $count];
            }
        }

        return ['brand' => $brand, 'total' => $counts['total'], 'categories' => $navigation];
    }

    /**
     * @param  Collection<int|string, Category>  $categories
     * @return array{total: int, categories: array<int, int>}
     */
    private function countsByCategory(Collection $categories, ?Brand $brand): array
    {
        $products = $this->catalog->scope([]);
        if ($brand !== null) {
            $products->where('brand_id', $brand->id);
        }
        $directCounts = $products->select('category_id')->selectRaw('COUNT(*) AS product_count')->groupBy('category_id')->get();
        $counts = [];
        $total = 0;
        foreach ($directCounts as $direct) {
            $rawCount = $direct->getAttribute('product_count');
            $count = is_numeric($rawCount) ? (int) $rawCount : 0;
            $total += $count;
            $categoryId = $direct->category_id;
            $visited = [];
            while ($categoryId !== null && ! isset($visited[$categoryId])) {
                $visited[$categoryId] = true;
                $category = $categories->get($categoryId);
                if ($category === null) {
                    break;
                }
                $counts[$categoryId] = ($counts[$categoryId] ?? 0) + $count;
                $categoryId = $category->parent_id;
            }
        }

        return ['total' => $total, 'categories' => $counts];
    }
}
