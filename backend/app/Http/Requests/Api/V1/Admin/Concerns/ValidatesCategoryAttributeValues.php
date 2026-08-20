<?php

namespace App\Http\Requests\Api\V1\Admin\Concerns;

use App\Models\Attribute;
use App\Models\Product;
use App\Services\AttributeValueValidator;
use Illuminate\Validation\Validator;

trait ValidatesCategoryAttributeValues
{
    /** @param array<int, array{attribute_id: mixed, value: mixed}> $values */
    protected function validateCategoryAttributeValues(Validator $validator, Product $product, array $values, string $field, bool $requireAll): void
    {
        $assigned = $product->category->attributes()->with('options')->get()->keyBy('id');
        $submittedIds = collect($values)->pluck('attribute_id')->map(static fn (mixed $id): int => (int) $id);

        foreach ($values as $index => $item) {
            $attribute = $assigned->get((int) $item['attribute_id']);
            if (! $attribute instanceof Attribute) {
                $validator->errors()->add("{$field}.{$index}.attribute_id", 'The attribute is not assigned to the product category.');

                continue;
            }

            $this->validateCategoryAttributeValue($validator, "{$field}.{$index}.value", $attribute, $item['value']);
        }

        if (! $requireAll) {
            return;
        }

        foreach ($assigned->where('is_required', true) as $attribute) {
            if (! $submittedIds->contains($attribute->id)) {
                $validator->errors()->add($field, "The required attribute {$attribute->name} is missing.");
            }
        }
    }

    private function validateCategoryAttributeValue(Validator $validator, string $key, Attribute $attribute, mixed $value): void
    {
        $valid = app(AttributeValueValidator::class)->isValid(
            $attribute->type,
            $value,
            $attribute->options->pluck('value')->all(),
        );

        if (! $valid) {
            $validator->errors()->add($key, "The value does not match the {$attribute->type} attribute type.");
        }
    }
}
