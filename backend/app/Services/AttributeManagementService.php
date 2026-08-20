<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariantAttributeValue;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributeManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly AttributeValueValidator $valueValidator,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Attribute
    {
        return DB::transaction(function () use ($actor, $attributes): Attribute {
            $options = Arr::pull($attributes, 'options', []);
            $attribute = Attribute::query()->create($attributes);
            $this->replaceOptions($attribute, $options);
            $this->auditLogService->record($actor, 'attribute.created', $attribute);

            return $attribute->load('options');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Attribute $attribute, array $attributes): Attribute
    {
        return DB::transaction(function () use ($actor, $attribute, $attributes): Attribute {
            $attribute = $this->lockForDefinitionMutation($attribute);

            $hasOptions = array_key_exists('options', $attributes);
            $options = Arr::pull($attributes, 'options', []);
            $typeBefore = $attribute->type;
            $isRequiredBefore = $attribute->is_required;
            $isVisibleOnProductPageBefore = $attribute->is_visible_on_product_page;
            $optionValuesBefore = $attribute->options()->pluck('value')->all();
            $typeAfter = $attributes['type'] ?? $typeBefore;
            $optionValuesAfter = in_array($typeAfter, ['select', 'multiselect'], true)
                ? ($hasOptions ? array_column($options, 'value') : $optionValuesBefore)
                : [];

            if ($typeAfter !== $typeBefore || $hasOptions) {
                $this->ensureExistingValuesRemainValid(
                    $attribute,
                    $typeAfter,
                    $optionValuesAfter,
                    $typeAfter !== $typeBefore ? 'type' : 'options',
                );
            }

            $attribute->fill($attributes)->save();

            if (! $attribute->acceptsOptions()) {
                $attribute->options()->delete();
            } elseif ($hasOptions) {
                $this->replaceOptions($attribute, $options);
            }

            $metadata = [];
            if ($typeBefore !== $attribute->type) {
                $metadata['type_before'] = $typeBefore;
                $metadata['type_after'] = $attribute->type;
            }
            if ($isRequiredBefore !== $attribute->is_required) {
                $metadata['is_required_before'] = $isRequiredBefore;
                $metadata['is_required_after'] = $attribute->is_required;
            }
            if ($isVisibleOnProductPageBefore !== $attribute->is_visible_on_product_page) {
                $metadata['is_visible_on_product_page_before'] = $isVisibleOnProductPageBefore;
                $metadata['is_visible_on_product_page_after'] = $attribute->is_visible_on_product_page;
            }
            if ($hasOptions || $optionValuesBefore !== $optionValuesAfter) {
                $metadata['options_replaced'] = true;
                $metadata['option_values_before'] = $optionValuesBefore;
                $metadata['option_values_after'] = $optionValuesAfter;
            }
            $this->auditLogService->record($actor, 'attribute.updated', $attribute, $metadata);

            return $attribute->load('options');
        });
    }

    public function delete(User $actor, Attribute $attribute): void
    {
        DB::transaction(function () use ($actor, $attribute): void {
            $attribute = $this->lockForDefinitionMutation($attribute);
            if ($this->attributeHasValues($attribute)) {
                throw ValidationException::withMessages([
                    'attribute' => ['An attribute with existing product or variant values cannot be deleted.'],
                ]);
            }
            $this->auditLogService->record($actor, 'attribute.deleted', $attribute, [
                'type' => $attribute->type,
                'is_required' => $attribute->is_required,
                'is_visible_on_product_page' => $attribute->is_visible_on_product_page,
                'option_values' => $attribute->options()->pluck('value')->all(),
            ]);
            $attribute->delete();
        });
    }

    /** @param array<int, array<string, mixed>> $options */
    private function replaceOptions(Attribute $attribute, array $options): void
    {
        if (! $attribute->acceptsOptions() && $options !== []) {
            throw ValidationException::withMessages(['options' => ['Options are available only for select and multiselect attributes.']]);
        }

        if ($attribute->acceptsOptions() && $options === []) {
            throw ValidationException::withMessages(['options' => ['Select attributes must have at least one option.']]);
        }

        $attribute->options()->delete();
        $attribute->options()->createMany(array_map(static fn (array $option): array => [
            'value' => $option['value'],
            'label' => $option['label'],
            'sort_order' => $option['sort_order'] ?? 0,
        ], $options));
    }

    /** @param array<int, string> $optionValues */
    private function ensureExistingValuesRemainValid(Attribute $attribute, string $type, array $optionValues, string $field): void
    {
        $values = ProductAttributeValue::query()->where('attribute_id', $attribute->id)->pluck('value')
            ->merge(ProductVariantAttributeValue::query()->where('attribute_id', $attribute->id)->pluck('value'));

        if ($values->contains(fn (mixed $value): bool => ! $this->valueValidator->isValid($type, $value, $optionValues))) {
            throw ValidationException::withMessages([
                $field => ['The change would invalidate existing product or variant attribute values.'],
            ]);
        }
    }

    private function lockForDefinitionMutation(Attribute $attribute): Attribute
    {
        $lockedCategoryIds = $attribute->categories()->pluck('categories.id')->sort()->values();
        Category::query()->whereIn('id', $lockedCategoryIds->all())->orderBy('id')->lockForUpdate()->get();
        Product::query()->whereIn('category_id', $lockedCategoryIds->all())->orderBy('id')->lockForUpdate()->get();
        $attribute = Attribute::query()->whereKey($attribute->id)->lockForUpdate()->firstOrFail();

        if ($attribute->categories()->whereNotIn('categories.id', $lockedCategoryIds)->exists()) {
            throw ValidationException::withMessages([
                'type' => ['The attribute category assignments changed concurrently. Retry the request.'],
            ]);
        }

        return $attribute;
    }

    private function attributeHasValues(Attribute $attribute): bool
    {
        return ProductAttributeValue::query()->where('attribute_id', $attribute->id)->exists()
            || ProductVariantAttributeValue::query()->where('attribute_id', $attribute->id)->exists();
    }
}
