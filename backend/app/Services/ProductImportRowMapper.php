<?php

namespace App\Services;

use App\Enums\ProductUnit;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductWorkbookSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Resolves workbook references and maps one normalized row to validated catalogue payloads.
 *
 * @phpstan-import-type ProductPayload from ProductImportService
 * @phpstan-import-type AttributePayload from ProductImportService
 */
final class ProductImportRowMapper
{
    public function __construct(private readonly ProductImportValueParser $values) {}

    /** @param list<string> $headers
     * @return array<string, Attribute>
     */
    public function attributesFor(array $headers): array
    {
        $slugs = collect($headers)
            ->filter(static fn (string $header): bool => str_starts_with($header, 'attribute.'))
            ->map(static fn (string $header): string => substr($header, 10))
            ->values();
        if ($slugs->contains('')) {
            throw ValidationException::withMessages(['file' => ['Столбец характеристики должен иметь формат attribute.<slug>.']]);
        }

        $attributes = Attribute::query()->with('options')->whereIn('slug', $slugs)->get()
            ->mapWithKeys(static fn (Attribute $attribute): array => [$attribute->slug => $attribute]);
        $missing = $slugs->diff(array_keys($attributes->all()));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['file' => ['Неизвестные характеристики: '.$missing->implode(', ').'.']]);
        }

        return $attributes->all();
    }

    /** @param array<string, mixed> $values
     * @param  array<string, array<string, mixed>>  $seoProducts
     * @return array<string, mixed>
     */
    public function localize(array $values, array $seoProducts, ?Product $product, int $row): array
    {
        $sku = $this->values->nullableString($values['sku'] ?? null);
        $seo = $sku === null ? [] : ($seoProducts[$sku] ?? []);
        if ($product === null && ($sku === null || $this->values->nullableString($seo['URL товара (slug)'] ?? null) === null)) {
            $this->values->rowError($row, 'товар с указанным SKU не найден. Для нового товара заполните временный SKU и URL товара на соответствующей строке листа SEO товаров. SKU существующего товара нельзя изменять.');
        }
        $values['slug'] = $this->values->nullableString($seo['URL товара (slug)'] ?? null) ?? $product?->slug;
        $units = array_flip(ProductWorkbookSchema::UNIT_LABELS);
        $unit = $this->values->nullableString($values['unit'] ?? null);
        $values['unit'] = $unit === null ? null : ($units[$unit] ?? $unit);
        $values['category_id'] = null;
        $values['brand_id'] = null;
        foreach (['category' => 'Категория', 'brand' => 'Бренд'] as $field => $label) {
            $name = $this->values->nullableString($values[$field.'_name']);
            if ($field === 'brand' && $name === null) {
                $values['brand_slug'] = null;

                continue;
            }
            $seoSlugHeader = $field === 'category' ? 'URL категории (slug)' : 'URL бренда (slug)';
            $slug = $this->values->nullableString($seo[$seoSlugHeader] ?? null);
            if ($name !== null && $name === $this->values->nullableString($seo[$label] ?? null) && $slug !== null) {
                $values[$field.'_slug'] = $slug;

                continue;
            }
            $model = $field === 'category' ? Category::class : Brand::class;
            $matches = $name === null ? collect() : $model::query()->where('name', $name)->limit(2)->get();
            if ($matches->count() !== 1) {
                $this->values->rowError($row, "{$label} «{$name}» не найдена или название неоднозначно. Укажите точное уникальное название либо название и URL на листе SEO товаров.");
            }
            $match = $matches->first();
            if (! $match instanceof Category && ! $match instanceof Brand) {
                $this->values->rowError($row, "{$label} «{$name}» не найдена или название неоднозначно.");
            }
            $values[$field.'_slug'] = $match->slug;
        }

        return $values;
    }

    /** @param array<string, mixed> $values */
    public function resolveProduct(array $values, int $row): ?Product
    {
        $id = $this->values->nullableInteger($values['id'] ?? null, $row, 'id');
        $sku = $this->values->nullableString($values['sku']);
        $byId = $id === null ? null : Product::query()->find($id);
        $bySku = $sku === null ? null : Product::query()->where('sku', $sku)->first();

        if ($byId !== null && $bySku !== null && $byId->id !== $bySku->id) {
            $this->values->rowError($row, 'id и sku относятся к разным товарам.');
        }
        if ($byId !== null && $sku !== null && $bySku === null) {
            $this->values->rowError($row, 'sku не совпадает с товаром, найденным по id.');
        }

        return $byId ?? $bySku;
    }

    /** @param array<string, mixed> $values */
    public function resolveCategory(array $values, int $row): Category
    {
        $id = $this->values->nullableInteger($values['category_id'], $row, 'category_id');
        $slug = $this->values->nullableString($values['category_slug']);
        $byId = $id === null ? null : Category::query()->find($id);
        $bySlug = $slug === null ? null : Category::query()->where('slug', $slug)->first();

        if ($byId !== null && $bySlug !== null && $byId->id !== $bySlug->id) {
            $this->values->rowError($row, 'category_id и category_slug относятся к разным категориям.');
        }
        if ($byId !== null && $slug !== null && $bySlug === null) {
            $this->values->rowError($row, 'category_slug не совпадает с категорией, найденной по category_id.');
        }
        $category = $bySlug ?? $byId;
        if ($category === null) {
            $this->values->rowError($row, 'категория не найдена по category_slug или category_id.');
        }

        return $category;
    }

    /** @param array<string, mixed> $values */
    public function resolveBrand(array $values, int $row): ?Brand
    {
        $id = $this->values->nullableInteger($values['brand_id'], $row, 'brand_id');
        $slug = $this->values->nullableString($values['brand_slug']);
        if ($id === null && $slug === null) {
            return null;
        }
        $byId = $id === null ? null : Brand::query()->find($id);
        $bySlug = $slug === null ? null : Brand::query()->where('slug', $slug)->first();
        if ($byId !== null && $bySlug !== null && $byId->id !== $bySlug->id) {
            $this->values->rowError($row, 'brand_id и brand_slug относятся к разным брендам.');
        }
        if ($byId !== null && $slug !== null && $bySlug === null) {
            $this->values->rowError($row, 'brand_slug не совпадает с брендом, найденным по brand_id.');
        }
        $brand = $bySlug ?? $byId;
        if ($brand === null) {
            $this->values->rowError($row, 'бренд не найден по brand_slug или brand_id.');
        }

        return $brand;
    }

    /** @param array<string, mixed> $values
     * @param  list<int|string>  $previousProductIds
     * @return ProductPayload
     */
    public function productPayload(array $values, Category $category, ?Brand $brand, ?Product $product, int $row, array $previousProductIds = []): array
    {
        $labels = array_map(static fn (string $label): string => '«'.$label.'»', ProductWorkbookSchema::MANAGER_HEADERS + [
            'slug' => 'Адрес товара', 'category_id' => 'Категория', 'brand_id' => 'Бренд',
        ]);
        $payload = [
            'category_id' => $category->id,
            'brand_id' => $brand?->id,
            'name' => $this->values->requiredString($values['name'] ?? null, $row, $labels['name']),
            'slug' => $this->values->requiredString($values['slug'] ?? null, $row, $labels['slug']),
            'description' => $this->values->nullableString($values['description'] ?? null),
            'article_number' => $this->values->nullableString($values['article_number'] ?? null),
            'barcode' => $this->values->nullableString($values['barcode'] ?? null),
            'unit' => $this->values->requiredString($values['unit'] ?? null, $row, $labels['unit']),
            'price' => $this->values->requiredString($values['price'] ?? null, $row, $labels['price']),
            'old_price' => $this->values->nullableString($values['old_price'] ?? null),
            'stock_quantity' => $this->values->requiredInteger($values['stock_quantity'] ?? null, $row, $labels['stock_quantity']),
            'is_active' => $this->values->boolean($values['is_active'] ?? null, $row, $labels['is_active']),
            'is_on_sale' => $this->values->boolean($values['is_on_sale'] ?? null, $row, $labels['is_on_sale']),
        ];

        $uniqueSlug = Rule::unique('products', 'slug')->whereNotIn('id', $previousProductIds);
        $uniqueArticle = Rule::unique('products', 'article_number')->whereNotIn('id', $previousProductIds);
        $uniqueBarcode = Rule::unique('products', 'barcode')->whereNotIn('id', $previousProductIds);
        if ($product !== null) {
            $uniqueSlug->ignore($product->id);
            $uniqueArticle->ignore($product->id);
            $uniqueBarcode->ignore($product->id);
        }
        $validator = Validator::make($payload, [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $uniqueSlug],
            'description' => ['nullable', 'string', 'max:10000'],
            'article_number' => ['nullable', 'string', 'max:100', $uniqueArticle],
            'barcode' => ['nullable', 'string', 'regex:/^(?:[0-9]{8}|[0-9]{12,14})$/', $uniqueBarcode],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'old_price' => ['nullable', 'numeric', 'gte:price', 'max:9999999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'is_active' => ['required', 'boolean'],
            'is_on_sale' => ['required', 'boolean'],
        ], ['numeric' => 'Поле :attribute должно содержать число.', 'gte' => 'Значение поля :attribute должно быть не меньше :value.'], $labels);
        if ($validator->fails()) {
            $this->values->rowError($row, $validator->errors()->first());
        }

        return $payload;
    }

    /** @param array<string, mixed> $values
     * @param  array<string, Attribute>  $attributes
     * @return AttributePayload
     */
    public function attributePayload(array $values, array $attributes, int $row, bool $localized = false): array
    {
        $payload = [];
        foreach ($attributes as $slug => $attribute) {
            $label = '«'.$attribute->name.($attribute->unit ? ' ('.$attribute->unit.')' : '').'»';
            $cell = $values['attribute.'.$slug] ?? null;
            if ($cell === null || (is_string($cell) && trim($cell) === '')) {
                continue;
            }
            $value = match ($attribute->type) {
                'string', 'text' => $this->values->requiredString($cell, $row, $label),
                'select' => $localized ? $this->optionValue($attribute, $cell, $row) : $this->values->requiredString($cell, $row, $label),
                'integer' => $this->values->requiredInteger($cell, $row, $label),
                'decimal' => $this->values->decimal($cell, $row, $label),
                'boolean' => $this->values->boolean($cell, $row, $label),
                'multiselect' => $localized ? $this->optionValues($attribute, $cell, $row) : $this->values->multiselect($cell, $row, $label),
                'date' => $this->values->date($cell, $row, $label),
                default => $this->values->rowError($row, "тип характеристики {$label} не поддерживается."),
            };
            $payload[] = ['attribute_id' => $attribute->id, 'value' => $value];
        }

        return $payload;
    }

    private function optionValue(Attribute $attribute, mixed $cell, int $row): string
    {
        $label = $this->values->requiredString($cell, $row, $attribute->name);
        $matches = $attribute->options->filter(static fn ($option): bool => $option->label === $label);
        $option = $matches->count() === 1 ? $matches->first() : null;
        if (! $option instanceof AttributeOption) {
            $this->values->rowError($row, "значение «{$label}» характеристики «{$attribute->name}» не найдено или неоднозначно.");
        }

        return $option->value;
    }

    /** @return list<string> */
    private function optionValues(Attribute $attribute, mixed $cell, int $row): array
    {
        $text = $this->values->requiredString($cell, $row, $attribute->name);

        return array_map(fn (?string $label): string => $this->optionValue($attribute, trim($label ?? ''), $row), str_getcsv($text, ';', '"', ''));
    }
}
