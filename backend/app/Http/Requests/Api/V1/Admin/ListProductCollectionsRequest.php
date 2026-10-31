<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListProductCollectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', ProductCollection::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'brand_id' => ['nullable', 'integer', 'exists:brands,id'], 'is_active' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1']];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->string('search')->toString())]);
        }
    }
}
