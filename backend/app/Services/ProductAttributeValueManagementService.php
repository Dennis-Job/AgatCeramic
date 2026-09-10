<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductGroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductAttributeValueManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CatalogAttributeIntegrityService $integrityService,
        private readonly ProductGroupManagementService $productGroupService,
    ) {}

    /** @param array<int, array{attribute_id: int, value: mixed}> $attributes */
    public function replace(User $actor, Product $product, array $attributes): Product
    {
        return DB::transaction(function () use ($actor, $product, $attributes): Product {
            $category = Category::query()->whereKey($product->category_id)->lockForUpdate()->firstOrFail();
            [$product, $group, $groupProducts] = $this->lockProductContext($product);
            if ($product->category_id !== $category->id) {
                throw ValidationException::withMessages(['attributes' => ['Категория товара была изменена параллельно. Повторите запрос.']]);
            }
            $product->setRelation('category', $category);

            $attributeIds = collect(array_column($attributes, 'attribute_id'));
            $this->integrityService->assertValuesMatchCategory($product, $attributes, 'attributes', $product->is_active);
            $product->attributeValues()->whereNotIn('attribute_id', $attributeIds)->delete();

            foreach ($attributes as $attribute) {
                $product->attributeValues()->updateOrCreate(
                    ['attribute_id' => $attribute['attribute_id']],
                    ['value' => $attribute['value']],
                );
            }

            if ($group !== null) {
                $axisIds = collect($this->integerIds($group->axes()->pluck('attributes.id')->all()));
                $sharedAttributeIds = collect($this->integerIds($category->attributes()->pluck('attributes.id')->all()))
                    ->diff($axisIds);
                $sharedValues = collect($attributes)
                    ->filter(fn (array $attribute): bool => $sharedAttributeIds->contains($attribute['attribute_id']));
                $submittedSharedIds = collect($this->integerIds($sharedValues->pluck('attribute_id')->all()));
                $requiredSharedIds = collect($this->integerIds($category->attributes()->wherePivot('is_required', true)->pluck('attributes.id')->all()))
                    ->diff($axisIds);

                if ($groupProducts->contains(fn (Product $member): bool => $member->is_active)
                    && $requiredSharedIds->diff($submittedSharedIds)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'attributes' => ['Общие обязательные характеристики нельзя очистить, пока в группе есть опубликованные товары.'],
                    ]);
                }

                foreach ($groupProducts->where('id', '!=', $product->id) as $member) {
                    $member->attributeValues()->whereIn('attribute_id', $sharedAttributeIds)
                        ->whereNotIn('attribute_id', $submittedSharedIds)->delete();
                    foreach ($sharedValues as $attribute) {
                        $member->attributeValues()->updateOrCreate(
                            ['attribute_id' => $attribute['attribute_id']],
                            ['value' => $attribute['value']],
                        );
                    }
                }

                $this->productGroupService->revalidate($group);
            }

            $this->auditLogService->record($actor, 'product.attributes-updated', $product, [
                'attribute_ids' => $attributeIds->all(),
                'synchronized_product_ids' => $group === null
                    ? []
                    : $groupProducts->where('id', '!=', $product->id)->pluck('id')->values()->all(),
            ]);

            return $product->load(['attributeValues.attribute.options']);
        });
    }

    /** @return array{Product, ?ProductGroup, EloquentCollection<int, Product>} */
    private function lockProductContext(Product $product): array
    {
        $membership = ProductGroupMember::query()->where('product_id', $product->id)->first();
        if ($membership === null) {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($lockedProduct->groupMembership()->exists()) {
                throw ValidationException::withMessages([
                    'attributes' => ['Состав группы вариантов был изменён параллельно. Повторите запрос.'],
                ]);
            }

            return [$lockedProduct, null, new EloquentCollection([$lockedProduct])];
        }

        $group = ProductGroup::query()->whereKey($membership->product_group_id)->lockForUpdate()->first();
        if ($group === null) {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            throw ValidationException::withMessages([
                'attributes' => ['Состав группы вариантов был изменён параллельно. Повторите запрос.'],
            ]);
        }

        $productIds = $group->products()->pluck('products.id')->push($product->id)->unique()->sort()->values();
        $products = Product::query()->whereKey($productIds)->orderBy('id')->lockForUpdate()->get();
        $lockedProduct = $products->firstWhere('id', $product->id);
        if ($lockedProduct === null) {
            throw ValidationException::withMessages(['attributes' => ['Товар больше не доступен. Обновите страницу.']]);
        }

        $currentGroupId = ProductGroupMember::query()->where('product_id', $lockedProduct->id)->value('product_group_id');
        if ($this->integerId($currentGroupId) !== $group->id) {
            throw ValidationException::withMessages([
                'attributes' => ['Состав группы вариантов был изменён параллельно. Повторите запрос.'],
            ]);
        }

        return [$lockedProduct, $group, $products];
    }

    private function integerId(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new \LogicException('Expected an integer database identifier.');
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<int>
     */
    private function integerIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $ids[] = $this->integerId($value);
        }

        return $ids;
    }
}
