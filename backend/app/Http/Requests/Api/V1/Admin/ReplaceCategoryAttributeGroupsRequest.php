<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceCategoryAttributeGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['attribute_groups' => ['present', 'array', 'max:100'], 'attribute_groups.*.id' => ['required', 'integer', 'distinct', 'exists:attribute_groups,id'], 'attribute_groups.*.sort_order' => ['sometimes', 'integer', 'min:0']];
    }

    /** @return array<int, array{id: int, sort_order?: int}> */
    public function attributeGroups(): array
    {
        $groups = [];
        foreach (array_keys($this->array('attribute_groups')) as $index) {
            $group = ['id' => $this->integer("attribute_groups.{$index}.id")];
            if ($this->has("attribute_groups.{$index}.sort_order")) {
                $group['sort_order'] = $this->integer("attribute_groups.{$index}.sort_order");
            }
            $groups[] = $group;
        }

        return $groups;
    }
}
