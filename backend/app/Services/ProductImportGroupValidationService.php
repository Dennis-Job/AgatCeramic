<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

/** Read-only, ordered group state for one workbook preflight. Instantiate for each import. */
class ProductImportGroupValidationService
{
    /**
     * @var array<int, array{
     *     axes: array<int|string, int>,
     *     shared: list<int>,
     *     required_shared: list<int>,
     *     members: array<int, array{is_active: bool, values: array<int, mixed>}>
     * }>
     */
    private array $groups = [];

    /**
     * @param  array{category_id: int|null, brand_id: int|null, is_active: bool}  $payload
     * @param  list<array{attribute_id: int, value: mixed}>  $attributePayload
     */
    public function validate(Product $product, array $payload, array $attributePayload): void
    {
        $membership = $product->groupMembership()->with('group')->first();
        if ($membership === null) {
            return;
        }

        foreach (['category_id', 'brand_id'] as $field) {
            if ($payload[$field] !== $product->{$field}) {
                throw ValidationException::withMessages([
                    $field => ['Перед изменением категории или бренда исключите товар из группы вариантов.'],
                ]);
            }
        }

        $groupId = $membership->product_group_id;
        if (! isset($this->groups[$groupId])) {
            $group = $membership->group;
            if ($group === null || $product->category === null) {
                throw ValidationException::withMessages([
                    'product_group' => ['Не удалось определить группу вариантов или категорию товара.'],
                ]);
            }
            $axisIds = $group->axes()->pluck('attributes.id')->filter(static fn (mixed $id): bool => is_int($id))->all();
            $categoryAttributes = $product->category->attributes()->get();
            $members = [];
            foreach ($group->products()->with('attributeValues')->get() as $member) {
                $members[$member->id] = [
                    'is_active' => $member->is_active,
                    'values' => $member->attributeValues->pluck('value', 'attribute_id')->mapWithKeys(static fn (mixed $value, mixed $attributeId): array => [(int) $attributeId => $value])->all(),
                ];
            }
            $this->groups[$groupId] = [
                'axes' => $axisIds,
                'shared' => array_values(array_diff($categoryAttributes->pluck('id')->filter(static fn (mixed $id): bool => is_int($id))->all(), $axisIds)),
                'required_shared' => array_values(array_diff(
                    $categoryAttributes->filter(static function ($attribute): bool {
                        $pivot = $attribute->getRelation('pivot');

                        return $pivot instanceof Pivot && (bool) $pivot->getAttribute('is_required');
                    })->pluck('id')->filter(static fn (mixed $id): bool => is_int($id))->all(),
                    $axisIds,
                )),
                'members' => $members,
            ];
        }

        $state = $this->groups[$groupId];
        $values = collect($attributePayload)->mapWithKeys(static fn (array $attribute): array => [$attribute['attribute_id'] => $attribute['value']])->all();
        // Application deactivates this row before replacing attributes; previous rows retain their final state.
        $state['members'][$product->id] = ['is_active' => false, 'values' => $values];
        if (collect($state['members'])->contains(static fn (array $member): bool => $member['is_active'])
            && array_diff($state['required_shared'], array_keys($values)) !== []) {
            throw ValidationException::withMessages([
                'attributes' => ['Общие обязательные характеристики нельзя очистить, пока в группе есть опубликованные товары.'],
            ]);
        }

        $tuples = [];
        foreach ($state['members'] as $memberId => &$member) {
            // Shared values are synchronized to all members by the real replacement service.
            foreach ($state['shared'] as $attributeId) {
                unset($member['values'][$attributeId]);
                if (array_key_exists($attributeId, $values)) {
                    $member['values'][$attributeId] = $values[$attributeId];
                }
            }
            if (array_diff($state['axes'], array_keys($member['values'])) !== []) {
                throw ValidationException::withMessages([
                    'attributes' => ["У товара {$memberId} не заполнено значение одной или нескольких осей группы."],
                ]);
            }
            $tuple = json_encode(array_map(static fn (int $id): mixed => $member['values'][$id], $state['axes']), JSON_THROW_ON_ERROR);
            if (isset($tuples[$tuple])) {
                throw ValidationException::withMessages([
                    'attributes' => ['Каждый товар должен иметь уникальное сочетание значений осей группы.'],
                ]);
            }
            $tuples[$tuple] = true;
        }
        unset($member);

        $state['members'][$product->id]['is_active'] = $payload['is_active'];
        $this->groups[$groupId] = $state;
    }
}
