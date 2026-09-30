<?php

namespace App\Http\Requests\Api\V1\Admin\Concerns;

use App\Http\Requests\Api\V1\Admin\UpdateHomePageRequest;
use App\Rules\SafeSiteUrl;
use App\Support\PageBlocks;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesPageContent
{
    /** @return array<string, list<mixed>> */
    private function contentRules(): array
    {
        $rules = [
            'blocks' => ['sometimes', 'present', 'array', 'max:40', 'list'],
            'blocks.*' => ['required', 'array:id,type,enabled,data'],
            'blocks.*.id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'distinct'],
            'blocks.*.type' => ['required', Rule::in(PageBlocks::TYPES)],
            'blocks.*.enabled' => ['required', 'boolean'],
            'blocks.*.data' => ['present', 'array'],
            'seo' => ['sometimes', 'required', 'array:title,description,og_title,og_description,og_image_url,og_image_media_id'],
            'seo.title' => ['required_with:seo', 'string', 'max:255'],
            'seo.description' => ['present_with:seo', 'string', 'max:2000'],
            'seo.og_title' => ['present_with:seo', 'string', 'max:255'],
            'seo.og_description' => ['present_with:seo', 'string', 'max:2000'],
            'seo.og_image_url' => ['nullable', 'string', 'max:2048', new SafeSiteUrl],
            'seo.og_image_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', 'image')->where('disk', 'public')],
        ];
        $blocks = $this->input('blocks', []);
        if (! is_array($blocks)) {
            return $rules;
        }
        $homeRules = (new UpdateHomePageRequest)->rules();
        foreach ($blocks as $index => $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                continue;
            }
            $type = $block['type'];
            $prefix = 'blocks.'.$index.'.data';
            if ($type === 'hero') {
                $rules[$prefix] = ['required', 'array:slider_id'];
                $rules[$prefix.'.slider_id'] = ['present', 'nullable', 'integer', Rule::exists('sliders', 'id')];
            } elseif (in_array($type, ['text', 'stores', 'catalog'], true)) {
                $keys = match ($type) {
                    'text' => 'title,body', 'stores' => 'title', 'catalog' => 'title,description'
                };
                $rules[$prefix] = ['required', 'array:'.$keys];
                $rules[$prefix.'.title'] = ['required', 'string', 'max:255'];
                if ($type !== 'stores') {
                    $rules[$prefix.'.'.($type === 'text' ? 'body' : 'description')] = ['present', 'string', 'max:100000'];
                }
            } else {
                foreach ($homeRules as $field => $fieldRules) {
                    if ($field !== $type && ! str_starts_with($field, $type.'.')) {
                        continue;
                    }
                    $rules[preg_replace('/^'.preg_quote($type, '/').'/', $prefix, $field)] = array_map(
                        static fn (mixed $rule): mixed => is_string($rule) ? str_replace('required_with:'.$type, 'required_with:'.$prefix, $rule) : $rule,
                        $fieldRules,
                    );
                }
                $rules[$prefix] = array_values(array_filter($rules[$prefix] ?? [], static fn (mixed $rule): bool => $rule !== 'sometimes'));
            }
        }

        return $rules;
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['title', 'slug', 'body', 'is_published', 'blocks', 'seo']) as $key) {
                $validator->errors()->add((string) $key, 'Неизвестное поле страницы.');
            }
            $seen = [];
            $blocks = $this->input('blocks', []);
            foreach (is_array($blocks) ? $blocks : [] as $index => $block) {
                $type = is_array($block) ? ($block['type'] ?? null) : null;
                if (! is_string($type) || $type === 'text') {
                    continue;
                }
                if (in_array($type, $seen, true)) {
                    $validator->errors()->add('blocks.'.$index.'.type', 'Этот тип блока может использоваться на странице один раз.');
                }
                $seen[] = $type;
            }
        }];
    }
}
