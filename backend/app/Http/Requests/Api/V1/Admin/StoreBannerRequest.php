<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\SafeSiteUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:2048', new SafeSiteUrl],
            'image_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', 'image')],
            'link_label' => ['nullable', 'required_with:link_url', 'string', 'max:255'],
            'link_url' => ['nullable', 'required_with:link_label', 'string', 'max:2048', new SafeSiteUrl],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
