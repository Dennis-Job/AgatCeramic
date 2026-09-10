<?php

namespace App\Http\Requests\Api\V1\Admin\Concerns;

use App\Models\Attribute;
use App\Models\Product;
use App\Services\AttributeValueValidator;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\Validator;

trait ValidatesCategoryAttributeValues
{
    /** @param array<int, array{attribute_id: mixed, value: mixed}> $values */
    protected function validateCategoryAttributeValues(Validator $validator, Product $product, array $values, string $field, bool $requireAll): void
    {
        $category = $product->category;
        if ($category === null) {
            return;
        }

        $assigned = $category->attributes()->with('options')->get()->keyBy('id');
        $submittedIds = [];

        foreach ($values as $index => $item) {
            $attributeId = $this->attributeId($item['attribute_id']);
            if ($attributeId === null) {
                continue;
            }
            $submittedIds[] = $attributeId;
            $attribute = $assigned->get($attributeId);
            if (! $attribute instanceof Attribute) {
                $validator->errors()->add("{$field}.{$index}.attribute_id", 'The attribute is not assigned to the product category.');

                continue;
            }

            $this->validateCategoryAttributeValue($validator, "{$field}.{$index}.value", $attribute, $item['value']);
        }

        if (! $requireAll) {
            return;
        }

        foreach ($assigned->filter(static function (Attribute $attribute): bool {
            $pivot = $attribute->getRelation('pivot');

            return $pivot instanceof Pivot && in_array($pivot->getAttribute('is_required'), [true, 1, '1'], true);
        }) as $attribute) {
            if (! in_array($attribute->id, $submittedIds, true)) {
                $validator->errors()->add($field, "The required attribute {$attribute->name} is missing.");
            }
        }
    }

    private function validateCategoryAttributeValue(Validator $validator, string $key, Attribute $attribute, mixed $value): void
    {
        $valid = app(AttributeValueValidator::class)->isValid(
            $attribute->type,
            $value,
            $attribute->options->pluck('value')->filter(static fn (mixed $value): bool => is_string($value))->values()->all(),
        );

        if (! $valid) {
            $validator->errors()->add($key, "The value does not match the {$attribute->type} attribute type.");
        }
    }

    private function attributeId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($id) ? $id : null;
    }
}
