<?php

namespace App\Http\Requests\Api\V1;

use App\Queries\PublicCatalogQuery;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class PublicCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'brand' => ['required_with:collection', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'collection' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'include_filters' => ['sometimes', 'boolean'],
            'category_descendants' => ['sometimes', 'boolean'],
            'in_stock' => ['sometimes', 'boolean'],
            'price_from' => ['sometimes', 'string', 'max:20', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'price_to' => ['sometimes', 'string', 'max:20', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'sort' => ['sometimes', Rule::in(['name_asc', 'name_desc', 'price_asc', 'price_desc', 'newest'])],
            'attributes' => ['sometimes', 'string', 'max:16384', 'json'],
        ];
    }

    /** @return list<\Closure(ValidationValidator): void> */
    public function after(PublicCatalogQuery $query): array
    {
        return [function (ValidationValidator $validator) use ($query): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $from = $this->input('price_from');
            $to = $this->input('price_to');
            if (is_string($from) && is_string($to) && BigDecimal::of($from)->isGreaterThan(BigDecimal::of($to))) {
                $validator->errors()->add('price_to', 'Верхняя граница цены должна быть не меньше нижней.');
            }
            if (! $this->has('attributes')) {
                return;
            }
            $raw = $this->input('attributes');
            $attributes = is_string($raw) ? json_decode($raw) : null;
            if (! $attributes instanceof \stdClass || count(get_object_vars($attributes)) > 20) {
                $validator->errors()->add('attributes', 'Укажите объект не более чем из 20 характеристик.');

                return;
            }
            $category = $this->input('category');
            $allowed = $query->filterAttributes(is_string($category) ? $category : null)->keyBy('id');
            foreach (get_object_vars($attributes) as $id => $filter) {
                $attribute = ctype_digit((string) $id) ? $allowed->get((int) $id) : null;
                if ($attribute === null || ! $filter instanceof \stdClass) {
                    $validator->errors()->add('attributes.'.$id, 'Характеристика недоступна для выбранной категории.');

                    continue;
                }
                $data = get_object_vars($filter);
                $rules = match ($attribute->type) {
                    'select', 'multiselect', 'boolean' => [
                        'values' => ['required', 'array', 'list', 'min:1', 'max:50'],
                        'values.*' => $attribute->type === 'boolean' ? ['required', 'string', Rule::in(['true', 'false'])] : ['required', 'string', 'max:255'],
                    ],
                    'integer', 'decimal', 'date' => [
                        'min' => ['required_without:max', 'filled', 'string', 'max:32', $attribute->type === 'date' ? 'date_format:Y-m-d' : ($attribute->type === 'integer' ? 'regex:/^-?\d{1,16}$/' : 'regex:/^-?\d{1,16}(?:\.\d{1,8})?$/')],
                        'max' => ['required_without:min', 'filled', 'string', 'max:32', $attribute->type === 'date' ? 'date_format:Y-m-d' : ($attribute->type === 'integer' ? 'regex:/^-?\d{1,16}$/' : 'regex:/^-?\d{1,16}(?:\.\d{1,8})?$/')],
                    ],
                    default => ['text' => ['required', 'string', 'max:200']],
                };
                $check = Validator::make($data, $rules);
                $keys = array_filter(array_keys($rules), fn ($key) => ! str_contains($key, '.'));
                if ($check->fails() || array_diff(array_keys($data), $keys) !== []) {
                    $validator->errors()->add('attributes.'.$id, 'Некорректный тип, формат или размер фильтра.');

                    continue;
                }
                if (isset($data['min'], $data['max']) && is_string($data['min']) && is_string($data['max']) && ($attribute->type === 'date' ? $data['min'] > $data['max'] : BigDecimal::of($data['min'])->isGreaterThan(BigDecimal::of($data['max'])))) {
                    $validator->errors()->add('attributes.'.$id, 'Верхняя граница должна быть не меньше нижней.');
                }
            }
        }];
    }
}
