<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListBrandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Brand::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $search = $this->input('search');
        if (is_string($search)) {
            $this->merge(['search' => trim($search)]);
        }
    }
}
