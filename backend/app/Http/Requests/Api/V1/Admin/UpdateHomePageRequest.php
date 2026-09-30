<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\SafeSiteUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $text = ['required', 'string', 'max:2000'];
        $short = ['required', 'string', 'max:255'];
        $link = ['required', 'string', 'max:2048', new SafeSiteUrl];
        $image = ['nullable', 'string', 'max:2048', new SafeSiteUrl];
        $imageId = ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', 'image')];

        $rules = [
            'hero_slider_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sliders', 'id')],
            'header' => ['sometimes', 'required', 'array:topbar_left,topbar_right,navigation,logo_media_id,logo_alt'],
            'header.topbar_left' => $short,
            'header.topbar_right' => $short,
            'header.logo_media_id' => $imageId,
            'header.logo_alt' => $short,
            'header.navigation' => ['required', 'array', 'min:1', 'max:16'],
            'header.navigation.*' => ['required', 'array:label,to'],
            'header.navigation.*.label' => $short,
            'header.navigation.*.to' => $link,
            'footer' => ['sometimes', 'required', 'array:tagline,explore_links,message,bottom_left,bottom_right'],
            'footer.tagline' => $short,
            'footer.explore_links' => ['required', 'array', 'max:12'],
            'footer.explore_links.*' => ['required', 'array:label,to'],
            'footer.explore_links.*.label' => $short,
            'footer.explore_links.*.to' => $link,
            'footer.message' => ['required', 'array:eyebrow,text,link_label,link_url'],
            'footer.message.eyebrow' => $short,
            'footer.message.text' => $text,
            'footer.message.link_label' => $short,
            'footer.message.link_url' => $link,
            'footer.bottom_left' => $short,
            'footer.bottom_right' => $short,
            'marquee' => ['sometimes', 'required', 'array:topics'],
            'marquee.topics' => ['required', 'array', 'min:1', 'max:20'],
            'marquee.topics.*' => $short,
            'categories' => ['sometimes', 'required', 'array:eyebrow,title,description,items'],
            'categories.eyebrow' => $short,
            'categories.title' => $short,
            'categories.description' => $text,
            'categories.items' => ['required', 'array', 'min:1', 'max:12'],
            'categories.items.*' => ['required', 'array:id,name,short_description,description,image_url,image_media_id,image_alt'],
            'categories.items.*.id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', 'distinct'],
            'categories.items.*.name' => $short,
            'categories.items.*.short_description' => $short,
            'categories.items.*.description' => $text,
            'categories.items.*.image_url' => $image,
            'categories.items.*.image_media_id' => $imageId,
            'categories.items.*.image_alt' => $short,
            'materials' => ['sometimes', 'required', 'array:eyebrow,title,description,note'],
            'materials.eyebrow' => $short,
            'materials.title' => $short,
            'materials.description' => $text,
            'materials.note' => $text,
            'promo' => ['sometimes', 'required', 'array:eyebrow,title,description,link_label,link_url'],
            'promo.eyebrow' => $short,
            'promo.title' => $short,
            'promo.description' => $text,
            'promo.link_label' => $short,
            'promo.link_url' => $link,
            'about' => ['sometimes', 'required', 'array:eyebrow,title,description,image_url,image_media_id,image_alt,link_label,link_url'],
            'about.eyebrow' => $short,
            'about.title' => $short,
            'about.description' => $text,
            'about.image_url' => $image,
            'about.image_media_id' => $imageId,
            'about.image_alt' => $short,
            'about.link_label' => $short,
            'about.link_url' => $link,
            'guide' => ['sometimes', 'required', 'array:eyebrow,title,items'],
            'guide.eyebrow' => $short,
            'guide.title' => $short,
            'guide.items' => ['required', 'array', 'min:1', 'max:12'],
            'guide.items.*' => ['required', 'array:title,description'],
            'guide.items.*.title' => $short,
            'guide.items.*.description' => $text,
            'seo' => ['sometimes', 'required', 'array:title,description,og_title,og_description,og_image_url,og_image_media_id'],
            'seo.title' => $short,
            'seo.description' => $text,
            'seo.og_title' => $short,
            'seo.og_description' => $text,
            'seo.og_image_url' => $image,
            'seo.og_image_media_id' => $imageId,
        ];

        foreach ($rules as $field => &$fieldRules) {
            if (! str_contains($field, '.') || str_contains($field, '*')) {
                continue;
            }
            $required = array_search('required', $fieldRules, true);
            if ($required !== false) {
                $parent = substr($field, 0, (int) strrpos($field, '.'));
                $fieldRules[$required] = 'required_with:'.$parent;
            }
        }

        return $rules;
    }
}
