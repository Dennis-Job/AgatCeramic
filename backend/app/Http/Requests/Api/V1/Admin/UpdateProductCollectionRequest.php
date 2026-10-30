<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateProductCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $collection = $this->route('collection');

        return $collection instanceof ProductCollection && Gate::allows('update', $collection);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'brand_id' => ['sometimes', 'required', 'integer', 'exists:brands,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'image_id' => ['sometimes', 'nullable', 'integer', Rule::exists('media', 'id')->where('kind', 'image')->where('disk', 'public')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
