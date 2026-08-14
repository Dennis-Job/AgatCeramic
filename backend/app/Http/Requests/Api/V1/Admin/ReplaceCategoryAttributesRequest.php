<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceCategoryAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'attributes' => ['present', 'array', 'max:500'],
            'attributes.*.id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'attributes.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
