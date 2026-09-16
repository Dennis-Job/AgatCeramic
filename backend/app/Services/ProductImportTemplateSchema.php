<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductWorkbookSchema;
use Illuminate\Validation\ValidationException;

final class ProductImportTemplateSchema
{
    public const BASE_HEADERS = [
        'name' => 'Название *', 'slug' => 'Slug (необязательно)', 'article_number' => 'Артикул',
        'barcode' => 'Штрихкод', 'description' => 'Описание', 'brand_name' => 'Бренд',
        'unit' => 'Единица продажи *', 'price' => 'Цена *', 'old_price' => 'Старая цена',
        'stock_quantity' => 'Остаток', 'is_active' => 'Активность', 'is_on_sale' => 'Распродажа',
    ];

    public const EDIT_HEADERS = ['sku' => 'SKU'];

    /** @return array<string, string> */
    public function headers(Category $category, bool $editing = false): array
    {
        $headers = $editing ? self::EDIT_HEADERS + self::BASE_HEADERS : self::BASE_HEADERS;
        $attributes = $category->attributes()->with('options')->get();
        foreach ($attributes as $attribute) {
            $label = $attribute->name.($attribute->unit ? ' ('.$attribute->unit.')' : '');
            if ($attributes->where('name', $attribute->name)->count() > 1 || in_array($label, self::BASE_HEADERS, true)) {
                $label .= ' [#'.$attribute->id.']';
            }
            if (data_get($attribute, 'pivot.is_required')) {
                $label .= ' *';
            }
            $count = $attribute->type === 'multiselect' ? max(1, $attribute->options->count()) : 1;
            for ($slot = 1; $slot <= $count; $slot++) {
                $key = 'attribute.'.$attribute->slug.($attribute->type === 'multiselect' ? '.'.$slot : '');
                $headers[$key] = $label.($attribute->type === 'multiselect' ? ' — '.$slot : '');
            }
        }
        if (count($headers) > 16384) {
            throw ValidationException::withMessages(['category_id' => ['Слишком много столбцов для формата Excel. Уменьшите число значений множественного выбора.']]);
        }

        return $headers;
    }

    /** @return list<array<string, mixed>> */
    public function editingRows(Category $category): array
    {
        $attributes = $category->attributes()->with('options')->get()->keyBy('id');

        return array_values(Product::query()->where('category_id', $category->id)->with(['brand', 'attributeValues'])->orderBy('sku')->get()
            ->map(function (Product $product) use ($attributes): array {
                $row = [
                    'sku' => $product->sku, 'name' => $product->name, 'slug' => $product->slug,
                    'article_number' => $product->article_number, 'barcode' => $product->barcode,
                    'description' => $product->description, 'brand_name' => $product->brand?->name,
                    'unit' => ProductWorkbookSchema::UNIT_LABELS[(string) $product->unit] ?? (string) $product->unit,
                    'price' => $product->price, 'old_price' => $product->old_price,
                    'stock_quantity' => $product->stock_quantity, 'is_active' => $product->is_active ? 'Да' : 'Нет',
                    'is_on_sale' => $product->is_on_sale ? 'Да' : 'Нет',
                ];
                foreach ($product->attributeValues as $value) {
                    $attribute = $attributes->get($value->attribute_id);
                    if ($attribute === null) {
                        continue;
                    }
                    $key = 'attribute.'.$attribute->slug;
                    if ($attribute->type === 'boolean') {
                        $row[$key] = $value->value ? 'Да' : 'Нет';
                    } elseif ($attribute->type === 'select') {
                        $option = $attribute->options->firstWhere('value', $value->value);
                        $row[$key] = $option === null ? null : $this->optionLabel($attribute, $option);
                    } elseif ($attribute->type === 'multiselect') {
                        foreach ((array) $value->value as $index => $optionValue) {
                            $option = $attribute->options->firstWhere('value', $optionValue);
                            $row[$key.'.'.($index + 1)] = $option === null ? null : $this->optionLabel($attribute, $option);
                        }
                    } else {
                        $row[$key] = $value->value;
                    }
                }

                return $row;
            })->all());
    }

    public function optionLabel(Attribute $attribute, AttributeOption $option): string
    {
        return $attribute->options->where('label', $option->label)->count() > 1
            ? $option->label.' [#'.$option->id.']' : $option->label;
    }
}
