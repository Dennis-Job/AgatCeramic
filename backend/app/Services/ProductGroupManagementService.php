<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductGroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductGroupManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function create(User $actor, array $payload): ProductGroup
    {
        return DB::transaction(function () use ($actor, $payload): ProductGroup {
            $group = ProductGroup::query()->create(['name' => $payload['name'], 'code' => $payload['code']]);
            $this->replaceComposition($group, $payload['axis_attribute_ids'], $payload['product_ids']);
            $this->auditLogService->record($actor, 'product-group.created', $group);

            return $this->load($group);
        });
    }

    public function update(User $actor, ProductGroup $group, array $payload): ProductGroup
    {
        return DB::transaction(function () use ($actor, $group, $payload): ProductGroup {
            $group = ProductGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $axisIds = $payload['axis_attribute_ids'] ?? $group->axes()->pluck('attributes.id')->all();
            $productIds = $payload['product_ids'] ?? $group->products()->pluck('products.id')->all();
            $group->fill(array_intersect_key($payload, array_flip(['name', 'code'])))->save();
            $this->replaceComposition($group, $axisIds, $productIds);
            $this->auditLogService->record($actor, 'product-group.updated', $group);

            return $this->load($group);
        });
    }

    public function delete(User $actor, ProductGroup $group): void
    {
        DB::transaction(function () use ($actor, $group): void {
            $group = ProductGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $this->auditLogService->record($actor, 'product-group.deleted', $group);
            $group->delete();
        });
    }

    public function revalidate(ProductGroup $group): void
    {
        $this->replaceComposition(
            $group,
            $group->axes()->pluck('attributes.id')->all(),
            $group->products()->pluck('products.id')->all(),
        );
    }

    private function replaceComposition(ProductGroup $group, array $axisIds, array $productIds): void
    {
        $products = Product::query()->whereKey($productIds)->orderBy('id')->lockForUpdate()
            ->with('attributeValues')->get();
        if ($products->count() !== count($productIds) || $products->count() < 2) {
            throw ValidationException::withMessages(['product_ids' => ['A product group requires at least two available products.']]);
        }

        $first = $products->first();
        if ($products->contains(fn (Product $product): bool => $product->category_id !== $first->category_id || $product->brand_id !== $first->brand_id)) {
            throw ValidationException::withMessages(['product_ids' => ['All grouped products must have the same category and brand.']]);
        }

        $conflict = ProductGroupMember::query()->whereIn('product_id', $productIds)
            ->where('product_group_id', '!=', $group->id)->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['product_ids' => ['A product can belong to only one product group.']]);
        }

        $axes = Attribute::query()->whereKey($axisIds)->orderBy('id')->lockForUpdate()->get();
        if ($axes->count() !== count($axisIds) || $axes->contains(fn (Attribute $attribute): bool => in_array($attribute->type, ['text', 'multiselect'], true))) {
            throw ValidationException::withMessages(['axis_attribute_ids' => ['Axes must be existing scalar attributes; text and multiselect are not supported.']]);
        }
        $categoryAttributeIds = $first->category->attributes()->pluck('attributes.id');
        if (collect($axisIds)->diff($categoryAttributeIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['axis_attribute_ids' => ['Every axis must be assigned to the products category.']]);
        }

        $valuesByProduct = $products->mapWithKeys(fn (Product $product): array => [
            $product->id => $product->attributeValues->mapWithKeys(fn ($value): array => [$value->attribute_id => $value->value]),
        ]);
        $tuples = [];
        foreach ($products as $product) {
            $values = $valuesByProduct[$product->id];
            if (collect($axisIds)->contains(fn (int $id): bool => ! $values->has($id))) {
                throw ValidationException::withMessages(['product_ids' => ["Product {$product->id} has no value for one or more group axes."]]);
            }
            $tuple = json_encode(collect($axisIds)->map(fn (int $id) => $values[$id])->all(), JSON_THROW_ON_ERROR);
            if (isset($tuples[$tuple])) {
                throw ValidationException::withMessages(['product_ids' => ['Every product must have a unique combination of group axis values.']]);
            }
            $tuples[$tuple] = true;
        }

        $nonAxisIds = $categoryAttributeIds->diff($axisIds);
        foreach ($nonAxisIds as $attributeId) {
            $expected = $this->canonical($valuesByProduct[$first->id]->get($attributeId));
            foreach ($products->skip(1) as $product) {
                if ($this->canonical($valuesByProduct[$product->id]->get($attributeId)) !== $expected) {
                    throw ValidationException::withMessages(['product_ids' => ['All non-axis category attribute values must be equal within a group.']]);
                }
            }
        }

        $group->axes()->sync(collect($axisIds)->mapWithKeys(fn (int $id, int $index): array => [$id => ['sort_order' => $index]])->all());
        $group->products()->sync($productIds);
    }

    private function canonical(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    public function load(ProductGroup $group): ProductGroup
    {
        return $group->load(['axes.options', 'products.category', 'products.brand', 'products.primaryImage', 'products.attributeValues.attribute.options']);
    }
}
