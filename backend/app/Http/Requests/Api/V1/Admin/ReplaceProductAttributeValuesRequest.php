<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReplaceProductAttributeValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'attributes' => ['required', 'array', 'max:500'],
            'attributes.*' => ['required', 'array:attribute_id,value'],
            'attributes.*.attribute_id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'attributes.*.value' => ['present'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Product $product */
            $product = $this->route('product');
            $assigned = $product->category->attributes()->with('options')->get()->keyBy('id');
            $submittedIds = collect($this->input('attributes', []))->pluck('attribute_id')->map(static fn (mixed $id): int => (int) $id);

            foreach ($this->input('attributes', []) as $index => $item) {
                $attribute = $assigned->get((int) $item['attribute_id']);
                if (! $attribute instanceof Attribute) {
                    $validator->errors()->add("attributes.{$index}.attribute_id", 'The attribute is not assigned to the product category.');

                    continue;
                }

                $this->validateValue($validator, $index, $attribute, $item['value']);
            }

            foreach ($assigned->where('is_required', true) as $attribute) {
                if (! $submittedIds->contains($attribute->id)) {
                    $validator->errors()->add('attributes', "The required attribute {$attribute->name} is missing.");
                }
            }
        }];
    }

    private function validateValue(Validator $validator, int $index, Attribute $attribute, mixed $value): void
    {
        $key = "attributes.{$index}.value";
        $valid = match ($attribute->type) {
            'text' => is_string($value) && mb_strlen($value) <= 10000,
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'select' => is_string($value) && $attribute->options->contains('value', $value),
            'multiselect' => is_array($value) && $value !== [] && count($value) <= 500
                && count($value) === count(array_unique($value))
                && collect($value)->every(fn (mixed $option): bool => is_string($option) && $attribute->options->contains('value', $option)),
            default => false,
        };

        if (! $valid) {
            $validator->errors()->add($key, "The value does not match the {$attribute->type} attribute type.");
        }
    }
}
