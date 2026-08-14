<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Product;
use App\Models\ProductRelation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReplaceProductRelationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'relations' => ['required', 'array', 'max:500'],
            'relations.*' => ['required', 'array:related_product_id,type,sort_order'],
            'relations.*.related_product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'relations.*.type' => ['required', 'string', Rule::in(ProductRelation::TYPES)],
            'relations.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Product $product */
            $product = $this->route('product');
            $relatedIds = collect($this->input('relations', []))->pluck('related_product_id')->map(static fn (mixed $id): int => (int) $id);

            foreach ($relatedIds as $index => $relatedId) {
                if ($relatedId === $product->id) {
                    $validator->errors()->add("relations.{$index}.related_product_id", 'A product cannot be related to itself.');
                }
            }

            $reverseProductIds = ProductRelation::query()
                ->where('related_product_id', $product->id)
                ->whereIn('product_id', $relatedIds)
                ->pluck('product_id');
            foreach ($relatedIds as $index => $relatedId) {
                if ($reverseProductIds->contains($relatedId)) {
                    $validator->errors()->add("relations.{$index}.related_product_id", 'A reverse product relation already exists.');
                }
            }
        }];
    }
}
