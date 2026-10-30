<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ProductAttributeValue;
use App\Models\ProductCollection;
use App\Models\ProductGroup;
use Illuminate\Support\Facades\Schema;

/**
 * @phpstan-type Candidate array{product_id: int, brand_id: int, category_id: int, name: string}
 * @phpstan-type Issue array{product_id?: int, group_id?: int, reason: string}
 * @phpstan-type Report array{checked: int, candidates: list<Candidate>, issues: list<Issue>, suggested_category_ids: list<int>, ready_for_cutover: bool}
 */
final class LegacyCollectionPreflight
{
    /** @return Report */
    public function scan(): array
    {
        $schemaReady = Schema::hasTable('product_collections') && Schema::hasColumn('products', 'collection_id');
        $relations = ['product.brand', 'product.category'];
        if ($schemaReady) {
            $relations[] = 'product.collection';
        }
        $rows = ProductAttributeValue::query()->whereHas('attribute', fn ($q) => $q->where('slug', 'kollektsiya'))
            ->with($relations)->orderBy('id')->get();
        $candidates = [];
        $issues = [];
        $names = [];
        $axesProducts = [];
        foreach (ProductGroup::query()->whereHas('axes', fn ($q) => $q->where('slug', 'kollektsiya'))->with('products:id')->get() as $group) {
            $issues[] = ['group_id' => $group->id, 'reason' => 'legacy_group_axis'];
            foreach ($group->products as $product) {
                $axesProducts[$product->id] = true;
            }
        }
        foreach ($rows as $row) {
            $product = $row->product;
            $value = $row->getAttribute('value');
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }
            $reason = ! is_string($value) ? 'non_string_value'
                : ($product?->brand === null || $product->brand_id === null ? 'missing_brand' : null);
            if ($reason !== null || $product === null || $product->brand_id === null) {
                $issues[] = ['product_id' => $row->product_id, 'reason' => $reason ?? 'missing_product'];

                continue;
            }
            if (mb_strlen($value) > 255) {
                $issues[] = ['product_id' => $product->id, 'reason' => 'name_too_long'];

                continue;
            }
            $key = $product->brand_id.'|'.mb_strtolower(trim($value));
            $names[$key][$value] = true;
            if (isset($axesProducts[$product->id])) {
                $issues[] = ['product_id' => $product->id, 'reason' => 'legacy_group_axis'];

                continue;
            }
            $candidates[] = ['product_id' => $product->id, 'brand_id' => $product->brand_id,
                'category_id' => $product->category_id, 'name' => $value];
        }
        if ($schemaReady) {
            foreach (ProductCollection::query()->get(['brand_id', 'name']) as $collection) {
                $key = $collection->brand_id.'|'.mb_strtolower(trim($collection->name));
                $names[$key][$collection->name] = true;
            }
        }
        $eligible = [];
        $byProduct = $rows->keyBy('product_id');
        foreach ($candidates as $candidate) {
            $key = $candidate['brand_id'].'|'.mb_strtolower(trim($candidate['name']));
            $product = $byProduct->get($candidate['product_id'])?->product;
            $reason = count($names[$key]) > 1 || trim($candidate['name']) !== $candidate['name'] ? 'ambiguous_name' : null;
            if ($schemaReady && $product?->collection_id !== null) {
                $current = $product->collection;
                if ($current !== null && $current->brand_id === $candidate['brand_id'] && $current->name === $candidate['name']) {
                    continue;
                }
                $reason = 'assignment_conflict';
            }
            if ($reason !== null) {
                $issues[] = ['product_id' => $candidate['product_id'], 'reason' => $reason];
            } else {
                $eligible[] = $candidate;
            }
        }
        $categoryIds = Category::query()->whereHas('attributes', fn ($q) => $q->where('slug', 'kollektsiya'))
            ->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => is_int($id) ? $id : throw new \LogicException('Expected integer category ID.'))->values()->all();

        return ['checked' => $rows->count(), 'candidates' => $eligible, 'issues' => $issues,
            'suggested_category_ids' => array_values($categoryIds), 'ready_for_cutover' => $eligible === [] && $issues === []];
    }
}
