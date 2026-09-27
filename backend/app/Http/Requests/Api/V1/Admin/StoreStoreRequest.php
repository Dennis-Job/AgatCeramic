<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Store::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
