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
            throw ValidationException::withMessages(['product_ids' => ['В группе вариантов должно быть не менее двух доступных товаров.']]);
        }

        $first = $products->first();
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
        $categoryAttributes = $first->category->attributes()->get(['attributes.id', 'attributes.name']);
        $categoryAttributeIds = $categoryAttributes->pluck('id');
        if (collect($axisIds)->diff($categoryAttributeIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['axis_attribute_ids' => ['Каждая ось должна быть назначена категории товаров.']]);
        }

        $valuesByProduct = $products->mapWithKeys(fn (Product $product): array => [
            $product->id => $product->attributeValues->mapWithKeys(fn ($value): array => [$value->attribute_id => $value->value]),
        ]);
        $tuples = [];
        foreach ($products as $product) {
            $values = $valuesByProduct[$product->id];
            if (collect($axisIds)->contains(fn (int $id): bool => ! $values->has($id))) {
                throw ValidationException::withMessages(['product_ids' => ["У товара {$product->id} не заполнено значение одной или нескольких осей группы."]]);
            }
            $tuple = json_encode(collect($axisIds)->map(fn (int $id) => $values[$id])->all(), JSON_THROW_ON_ERROR);
            if (isset($tuples[$tuple])) {
                throw ValidationException::withMessages(['product_ids' => ['Каждый товар должен иметь уникальное сочетание значений осей группы.']]);
            }
            $tuples[$tuple] = true;
        }

        $nonAxisIds = $categoryAttributeIds->diff($axisIds);
        foreach ($nonAxisIds as $attributeId) {
            $expected = $this->canonical($valuesByProduct[$first->id]->get($attributeId));
            foreach ($products->skip(1) as $product) {
                if ($this->canonical($valuesByProduct[$product->id]->get($attributeId)) !== $expected) {
                    $attributeName = $categoryAttributes->firstWhere('id', $attributeId)?->name ?? "ID {$attributeId}";
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

    public function load(ProductGroup $group): ProductGroup
    {
        return $group->load(['axes.options', 'products.category', 'products.brand', 'products.primaryImage', 'products.attributeValues.attribute.options']);
    }
}
