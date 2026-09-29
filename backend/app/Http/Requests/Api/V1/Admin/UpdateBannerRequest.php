<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Banner;
use App\Rules\SafeSiteUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $banner = $this->route('banner');
        $legacyImage = $banner instanceof Banner && $banner->image_url === $this->input('image_url');
        $legacyLink = $banner instanceof Banner && $banner->link_url === $this->input('link_url');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'eyebrow' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2048', new SafeSiteUrl($legacyImage)],
            'image_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists('media', 'id')->where('kind', 'image')],
            'link_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'link_url' => ['sometimes', 'nullable', 'string', 'max:2048', new SafeSiteUrl($legacyLink)],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
