<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Product;
use Illuminate\Support\Facades\Gate;

class ExportProductsRequest extends ListProductsRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Gate::allows('export', Product::class);
    }

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'per_page' => ['prohibited'],
            'skus' => $this->isMethod('POST') ? ['required', 'array', 'min:1', 'max:1000'] : ['missing'],
            'skus.*' => $this->isMethod('POST') ? ['bail', 'required', 'string', 'max:255', 'exists:products,sku'] : [],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return [
            'skus.*.exists' => 'Товар с SKU :input не найден.',
        ];
    }
}
