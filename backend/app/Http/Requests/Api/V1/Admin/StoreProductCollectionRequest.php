<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProductCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', ProductCollection::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'headline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'image_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', 'image')->where('disk', 'public')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
