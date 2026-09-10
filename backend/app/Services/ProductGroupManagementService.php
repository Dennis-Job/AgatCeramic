<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductGroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type CompositionPayload array{name: string, code: string, axis_attribute_ids: list<int>, product_ids: list<int>}
 * @phpstan-type BulkChange array{action: 'disband', group_id: int}|array{action: 'create'|'update', group_id: int|null, code: string, name: string, axis_attribute_ids: list<int>, product_ids: list<int>}
 */
class ProductGroupManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * @param  CompositionPayload  $payload
     */
    public function create(User $actor, array $payload): ProductGroup
    {
        return DB::transaction(function () use ($actor, $payload): ProductGroup {
            $group = ProductGroup::query()->create(['name' => $payload['name'], 'code' => $payload['code']]);
            $this->replaceComposition($group, $payload['axis_attribute_ids'], $payload['product_ids']);
            $this->auditLogService->record($actor, 'product-group.created', $group);

            return $this->load($group);
        });
    }

    /**
     * @param  array{name?: string, code?: string, axis_attribute_ids?: list<int>, product_ids?: list<int>}  $payload
     */
    public function update(User $actor, ProductGroup $group, array $payload): ProductGroup
    {
        return DB::transaction(function () use ($actor, $group, $payload): ProductGroup {
            $group = ProductGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $axisIds = $payload['axis_attribute_ids'] ?? $this->integerIds($group->axes()->pluck('attributes.id')->all());
            $productIds = $payload['product_ids'] ?? $this->integerIds($group->products()->pluck('products.id')->all());
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
            $this->integerIds($group->axes()->pluck('attributes.id')->all()),
            $this->integerIds($group->products()->pluck('products.id')->all()),
        );
    }

    /**
     * Applies a connected set of workbook changes in one transaction.  Existing
     * memberships are detached before compositions are replaced so a SKU can
     * move between groups in the same workbook without ever being persisted in
     * two groups.
     *
     * @param  list<BulkChange>  $changes
     */
    public function applyBulk(User $actor, array $changes): void
    {
        DB::transaction(function () use ($actor, $changes): void {
            $existingIds = collect($changes)->pluck('group_id')->filter(static fn (mixed $id): bool => is_int($id))->unique()->sort()->values()->all();
            $groups = ProductGroup::query()->whereIn('id', $existingIds)->lockForUpdate()->get()->keyBy('id');
            if ($groups->count() !== count($existingIds)) {
                throw ValidationException::withMessages(['groups' => ['Группа вариантов была удалена до обработки файла.']]);
            }

            // Detaching only the groups covered by this atomic component leaves
            // memberships of unrelated groups visible to replaceComposition.
            if ($existingIds !== []) {
                ProductGroupMember::query()->whereIn('product_group_id', $existingIds)->delete();
            }

            foreach ($changes as $change) {
                if ($change['action'] === 'disband') {
                    $group = $groups->get($change['group_id']);
                    if (! $group instanceof ProductGroup) {
                        throw ValidationException::withMessages(['groups' => ['Группа вариантов была удалена до обработки файла.']]);
                    }
                    $this->auditLogService->record($actor, 'product-group.deleted', $group);
                    $group->delete();

                    continue;
                }

                $group = $change['group_id'] !== null
                    ? $groups->get($change['group_id'])
                    : ProductGroup::query()->create(['name' => $change['name'], 'code' => $change['code']]);

                if (! $group instanceof ProductGroup) {
                    throw ValidationException::withMessages(['groups' => ['Группа вариантов была удалена до обработки файла.']]);
                }

                if ($change['group_id'] !== null) {
                    $group->fill(['name' => $change['name'], 'code' => $change['code']])->save();
                }
                $this->replaceComposition($group, $change['axis_attribute_ids'], $change['product_ids']);
                $this->auditLogService->record($actor, $change['group_id'] !== null ? 'product-group.updated' : 'product-group.created', $group);
            }
        });
    }

    /**
     * @param  list<int>  $axisIds
     * @param  list<int>  $productIds
     */
    private function replaceComposition(ProductGroup $group, array $axisIds, array $productIds): void
    {
        $products = Product::query()->whereKey($productIds)->orderBy('id')->lockForUpdate()
            ->with('attributeValues')->get();
        if ($products->count() !== count($productIds) || $products->count() < 2) {
            throw ValidationException::withMessages(['product_ids' => ['В группе вариантов должно быть не менее двух доступных товаров.']]);
        }

        $first = $products->firstOrFail();
        if ($products->contains(fn (Product $product): bool => $product->category_id !== $first->category_id || $product->brand_id !== $first->brand_id)) {
            throw ValidationException::withMessages(['product_ids' => ['Все товары группы должны относиться к одной категории и одному бренду.']]);
        }

        $conflict = ProductGroupMember::query()->whereIn('product_id', $productIds)
            ->where('product_group_id', '!=', $group->id)->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['product_ids' => ['Товар может входить только в одну группу вариантов.']]);
        }

        $axes = Attribute::query()->whereKey($axisIds)->orderBy('id')->lockForUpdate()->get();
        if ($axes->count() !== count($axisIds) || $axes->contains(fn (Attribute $attribute): bool => in_array($attribute->type, ['text', 'multiselect'], true))) {
            throw ValidationException::withMessages(['axis_attribute_ids' => ['Осями могут быть только существующие скалярные характеристики; текст и множественный выбор не поддерживаются.']]);
        }
        $category = $first->category;
        if ($category === null) {
            throw ValidationException::withMessages(['product_ids' => ['Не удалось определить категорию товаров группы.']]);
        }
        $categoryAttributes = $category->attributes()->get(['attributes.id', 'attributes.name']);
        $categoryAttributeIds = $this->integerIds($categoryAttributes->pluck('id')->all());
        if (array_diff($axisIds, $categoryAttributeIds) !== []) {
            throw ValidationException::withMessages(['axis_attribute_ids' => ['Каждая ось должна быть назначена категории товаров.']]);
        }

        $valuesByProduct = [];
        foreach ($products as $product) {
            $values = [];
            foreach ($product->attributeValues as $value) {
                $values[$value->attribute_id] = $value->value;
            }
            $valuesByProduct[$product->id] = $values;
        }
        $tuples = [];
        foreach ($products as $product) {
            $values = $valuesByProduct[$product->id];
            if (array_diff($axisIds, array_keys($values)) !== []) {
                throw ValidationException::withMessages(['product_ids' => ["У товара {$product->id} не заполнено значение одной или нескольких осей группы."]]);
            }
            $tuple = json_encode(array_map(fn (int $id): mixed => $values[$id], $axisIds), JSON_THROW_ON_ERROR);
            if (isset($tuples[$tuple])) {
                throw ValidationException::withMessages(['product_ids' => ['Каждый товар должен иметь уникальное сочетание значений осей группы.']]);
            }
            $tuples[$tuple] = true;
        }

        $nonAxisIds = array_values(array_diff($categoryAttributeIds, $axisIds));
        foreach ($nonAxisIds as $attributeId) {
            $expected = $this->canonical($valuesByProduct[$first->id][$attributeId] ?? null);
            foreach ($products->skip(1) as $product) {
                if ($this->canonical($valuesByProduct[$product->id][$attributeId] ?? null) !== $expected) {
                    $attribute = $categoryAttributes->firstWhere('id', $attributeId);
                    $attributeName = $attribute instanceof Attribute ? $attribute->name : "ID {$attributeId}";
                    throw ValidationException::withMessages([
                        'product_ids' => ["Значение общей характеристики «{$attributeName}» должно совпадать у всех товаров группы."],
                    ]);
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

    /**
     * @param  iterable<mixed>  $values
     * @return list<int>
     */
    private function integerIds(iterable $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $ids[] = $value;
            }
        }

        return $ids;
    }

    public function load(ProductGroup $group): ProductGroup
    {
        return $group->load(['axes.options', 'products.category', 'products.brand', 'products.primaryImage', 'products.attributeValues.attribute.options']);
    }
}
