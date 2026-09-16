<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductWorkbookSchema;
use Illuminate\Support\Str;

final class CategoryProductRowImporter
{
    public function __construct(
        private readonly ProductImportRowMapper $mapper,
        private readonly ProductImportValueParser $values,
        private readonly ProductImportTemplateService $templates,
        private readonly ProductImportExecutor $executor,
    ) {}

    /**
     * Create or update one category-template row. The caller owns its transaction and checkpoint.
     *
     * @param  array<string, mixed>  $rowValues
     */
    public function apply(User $actor, Category $category, array $rowValues, int $row, bool $editing = false): string
    {
        $name = $this->values->requiredString($rowValues['name'] ?? null, $row, 'Название');
        $category = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
        $product = null;
        if ($editing) {
            $sku = $this->values->requiredString($rowValues['sku'] ?? null, $row, 'SKU');
            $product = Product::query()->where('sku', $sku)->lockForUpdate()->first();
            if ($product === null || $product->category_id !== $category->id) {
                $this->values->rowError($row, 'SKU не относится к товару выбранной категории. Скачайте актуальный шаблон и не изменяйте SKU.');
            }
        }
        $sameName = Product::query()->where(fn ($query) => $query->where('name', $name)->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]));
        if ($product !== null) {
            $sameName->whereKeyNot($product->id);
        }
        if ($sameName->exists()) {
            $this->values->rowError($row, 'товар с таким наименованием уже существует.');
        }
        $rowValues['slug'] = $this->values->nullableString($rowValues['slug'] ?? null) ?? ($product === null ? Str::slug($name, '-', 'ru') : $product->slug);
        $sameSlug = Product::query()->where('slug', $rowValues['slug']);
        if ($product !== null) {
            $sameSlug->whereKeyNot($product->id);
        }
        if ($sameSlug->exists()) {
            $this->values->rowError($row, 'товар с таким slug уже существует.');
        }
        $unit = $this->values->nullableString($rowValues['unit'] ?? null);
        $rowValues['unit'] = $unit === null ? null : (array_flip(ProductWorkbookSchema::UNIT_LABELS)[$unit] ?? $unit);
        foreach (['stock_quantity' => 0, 'is_active' => false, 'is_on_sale' => false] as $field => $default) {
            if ($this->values->nullableString($rowValues[$field] ?? null) === null) {
                $rowValues[$field] = $default;
            }
        }
        $brandName = $this->values->nullableString($rowValues['brand_name'] ?? null);
        $brand = null;
        if ($brandName !== null) {
            $brands = Brand::query()->where('name', $brandName)->limit(2)->get();
            if ($brands->count() !== 1) {
                $this->values->rowError($row, 'выберите существующий бренд с однозначным названием.');
            }
            $brand = $brands->first();
        }
        $attributes = $category->attributes()->with('options')->get()
            ->mapWithKeys(static fn (Attribute $attribute): array => [$attribute->slug => $attribute])->all();
        foreach ($attributes as $slug => $attribute) {
            if (! in_array($attribute->type, ['select', 'multiselect'], true)) {
                continue;
            }
            $cells = $attribute->type === 'select'
                ? [$rowValues['attribute.'.$slug] ?? null]
                : collect($rowValues)->filter(fn (mixed $cell, string $key): bool => str_starts_with($key, 'attribute.'.$slug.'.'))->values()->all();
            $selected = [];
            foreach ($cells as $cell) {
                $label = $this->values->nullableString($cell);
                if ($label === null) {
                    continue;
                }
                $option = $attribute->options->first(fn ($option) => $this->templates->optionLabel($attribute, $option) === $label);
                if (! $option instanceof AttributeOption) {
                    $this->values->rowError($row, "значение «{$label}» характеристики «{$attribute->name}» отсутствует в текущем списке.");
                }
                $selected[] = $option->value;
            }
            $rowValues['attribute.'.$slug] = $selected === [] ? null : ($attribute->type === 'select' ? $selected[0] : json_encode(array_values(array_unique($selected)), JSON_THROW_ON_ERROR));
        }
        $payload = $this->mapper->productPayload($rowValues, $category, $brand, $product, $row);
        $attributePayload = $this->mapper->attributePayload($rowValues, $attributes, $row);
        $requiredAttributeIds = $category->attributes()->wherePivot('is_required', true)->pluck('attributes.id')->all();
        $missingAttributes = collect($attributes)->whereIn('id', $requiredAttributeIds)
            ->whereNotIn('id', collect($attributePayload)->pluck('attribute_id'));
        if ($missingAttributes->isNotEmpty()) {
            $this->values->rowError($row, 'обязательные характеристики не заполнены: '.$missingAttributes
                ->map(static fn (Attribute $attribute): string => '«'.$attribute->name.'»')->implode(', ').'.');
        }

        $result = $this->executor->applyWithResult($actor, $product?->id, $payload, $attributePayload);
        if ($product === null && Product::query()->whereKeyNot($result['product']->id)->where(fn ($query) => $query->where('name', $name)->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]))->exists()) {
            $this->values->rowError($row, 'товар с таким наименованием уже существует.');
        }

        return $result['operation'];
    }
}
