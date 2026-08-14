<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceCategoryAttributeGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['attribute_groups' => ['present', 'array', 'max:100'], 'attribute_groups.*.id' => ['required', 'integer', 'distinct', 'exists:attribute_groups,id'], 'attribute_groups.*.sort_order' => ['sometimes', 'integer', 'min:0']];
    }
}
