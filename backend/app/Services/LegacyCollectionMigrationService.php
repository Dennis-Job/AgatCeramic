<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type Report from LegacyCollectionPreflight
 * @phpstan-import-type Candidate from LegacyCollectionPreflight
 * @phpstan-import-type Issue from LegacyCollectionPreflight
 */
final class LegacyCollectionMigrationService
{
    public function __construct(
        private readonly LegacyCollectionPreflight $preflight,
        private readonly ProductCollectionManagementService $collections,
        private readonly CategoryManagementService $categories,
        private readonly ProductManagementService $products,
    ) {}

    /** @return Report */
    public function scan(): array
    {
        return $this->preflight->scan();
    }

    /**
     * The command requires maintenance/backup acknowledgement; transactions revalidate sources.
     *
     * @param  list<int>  $enableCategoryIds
     * @return array{checked: int, candidates: list<Candidate>, issues: list<Issue>, suggested_category_ids: list<int>, ready_for_cutover: bool, assigned: int}
     */
    public function apply(User $actor, array $enableCategoryIds): array
    {
        Gate::forUser($actor)->authorize('create', ProductCollection::class);

        return DB::transaction(function () use ($actor, $enableCategoryIds): array {
            $report = $this->scan();
            $allowed = array_intersect($enableCategoryIds, $report['suggested_category_ids']);
            foreach (Category::query()->whereKey($allowed)->orderBy('id')->lockForUpdate()->get() as $category) {
                $this->categories->update($actor, $category, ['uses_collections' => true]);
            }
            $assigned = 0;
            foreach ($report['candidates'] as $candidate) {
                $category = Category::query()->whereKey($candidate['category_id'])->lockForUpdate()->firstOrFail();
                if (! $category->uses_collections) {
                    continue;
                }
                $source = ProductAttributeValue::query()->where('product_id', $candidate['product_id'])
                    ->whereHas('attribute', fn ($q) => $q->where('slug', 'kollektsiya'))->lockForUpdate()->first();
                $product = Product::query()->whereKey($candidate['product_id'])->lockForUpdate()->firstOrFail();
                if ($source?->value !== $candidate['name'] || $product->brand_id !== $candidate['brand_id']
                    || $product->category_id !== $category->id || $product->collection_id !== null) {
                    throw ValidationException::withMessages(['collections' => ['Исходные данные изменились. Повторите предварительную проверку.']]);
                }
                $collection = ProductCollection::query()->where('brand_id', $candidate['brand_id'])->where('name', $candidate['name'])->first();
                $collection ??= $this->collections->create($actor, [
                    'brand_id' => $candidate['brand_id'], 'name' => $candidate['name'],
                    'slug' => rtrim(substr(Str::slug($candidate['name'], '-', 'ru') ?: 'collection', 0, 246), '-').'-'.substr(hash('sha256', $candidate['name']), 0, 8),
                    'headline' => null, 'description' => null, 'image_id' => null, 'is_active' => true,
                ]);
                $this->products->update($actor, $product, ['collection_id' => $collection->id], ['source' => 'legacy-collection-migration']);
                $assigned++;
            }

            return [...$this->scan(), 'assigned' => $assigned];
        }, 3);
    }
}
