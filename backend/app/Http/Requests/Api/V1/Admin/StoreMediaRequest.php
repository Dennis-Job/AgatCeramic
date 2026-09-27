<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $image = $this->input('kind') === 'image';

        return [
            'kind' => ['required', Rule::in(['image', 'document'])],
            'file' => $image
                ? ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240']
                : ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],
            'title' => ['required', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }
}
