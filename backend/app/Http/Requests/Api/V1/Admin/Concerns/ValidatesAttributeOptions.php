<?php

namespace App\Http\Requests\Api\V1\Admin\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesAttributeOptions
{
    protected function validateOptionsForType(Validator $validator): void
    {
        $type = $this->input('type', $this->route('attribute')?->type);

        if ($type === null) {
            return;
        }

        $options = $this->input('options');
        $acceptsOptions = in_array($type, ['select', 'multiselect'], true);

        if ($acceptsOptions && is_array($options) && count($options) === 0) {
            $validator->errors()->add('options', 'Select attributes must have at least one option.');
        }

        if (! $acceptsOptions && is_array($options)) {
            $validator->errors()->add('options', 'Options are available only for select and multiselect attributes.');
        }
    }
}
