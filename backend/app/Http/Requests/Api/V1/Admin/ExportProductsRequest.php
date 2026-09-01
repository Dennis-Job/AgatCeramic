<?php

namespace App\Http\Requests\Api\V1\Admin;

class ExportProductsRequest extends ListProductsRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'per_page' => ['prohibited'],
        ];
    }
}
