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

/** @phpstan-import-type AttributePayload from ProductImportService */
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
     * @param  array<string, mixed>  $auditContext
     */
    public function apply(User $actor, Category $category, array $rowValues, int $row, bool $editing = false, array $auditContext = []): string
    {
        $name = $this->values->requiredString($rowValues['name'] ?? null, $row, 'Название');
        $category = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
        $product = $editing ? $this->resolveProductForEdit($category, $rowValues['sku'] ?? null, $row) : null;
        $this->assertNameAvailable($name, $row, $product?->id);

        $rowValues['slug'] = $this->resolveAvailableSlug($rowValues['slug'] ?? null, $name, $product, $row);
        $rowValues = $this->normalizeProductFields($rowValues);
        $brand = $this->resolveBrand($rowValues['brand_name'] ?? null, $row);
        $attributes = $category->attributes()->with('options')->get()
            ->mapWithKeys(static fn (Attribute $attribute): array => [$attribute->slug => $attribute])->all();
        $rowValues = $this->normalizeAttributeOptions($rowValues, $attributes, $row);

        $payload = $this->mapper->productPayload($rowValues, $category, $brand, $product, $row);
        $attributePayload = $this->mapper->attributePayload($rowValues, $attributes, $row);
        $this->assertRequiredAttributes($category, $attributes, $attributePayload, $row);

        $result = $this->executor->applyWithResult($actor, $product?->id, $payload, $attributePayload, $auditContext);
        if ($product === null) {
            $this->assertNameAvailable($name, $row, $result['product']->id);
        }

        return $result['operation'];
    }

    private function resolveProductForEdit(Category $category, mixed $skuCell, int $row): Product
    {
        $sku = $this->values->requiredString($skuCell, $row, 'SKU');
        $product = Product::query()->where('sku', $sku)->lockForUpdate()->first();
        if ($product === null || $product->category_id !== $category->id) {
            $this->values->rowError($row, 'SKU не относится к товару выбранной категории. Скачайте актуальный шаблон и не изменяйте SKU.');
        }

        return $product;
    }

    private function assertNameAvailable(string $name, int $row, ?int $exceptProductId): void
    {
        $sameName = Product::query()->where(fn ($query) => $query->where('name', $name)->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]));
        if ($exceptProductId !== null) {
            $sameName->whereKeyNot($exceptProductId);
        }
        if ($sameName->exists()) {
            $this->values->rowError($row, 'товар с таким наименованием уже существует.');
        }
    }

    private function resolveAvailableSlug(mixed $slugCell, string $name, ?Product $product, int $row): string
    {
        $slug = $this->values->nullableString($slugCell) ?? ($product === null ? Str::slug($name, '-', 'ru') : $product->slug);
        $sameSlug = Product::query()->where('slug', $slug);
        if ($product !== null) {
            $sameSlug->whereKeyNot($product->id);
        }
        if ($sameSlug->exists()) {
            $this->values->rowError($row, 'товар с таким slug уже существует.');
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $rowValues
     * @return array<string, mixed>
     */
    private function normalizeProductFields(array $rowValues): array
    {
        $unit = $this->values->nullableString($rowValues['unit'] ?? null);
        $rowValues['unit'] = $unit === null ? null : (array_flip(ProductWorkbookSchema::UNIT_LABELS)[$unit] ?? $unit);
        foreach (['stock_quantity' => 0, 'is_active' => false, 'is_on_sale' => false] as $field => $default) {
            if ($this->values->nullableString($rowValues[$field] ?? null) === null) {
                $rowValues[$field] = $default;
            }
        }

        return $rowValues;
    }

    private function resolveBrand(mixed $brandCell, int $row): ?Brand
    {
        $name = $this->values->nullableString($brandCell);
        if ($name === null) {
            return null;
        }

        $brands = Brand::query()->where('name', $name)->limit(2)->get();
        if ($brands->count() !== 1) {
            $this->values->rowError($row, 'выберите существующий бренд с однозначным названием.');
        }

        return $brands->first();
    }

    /**
     * @param  array<string, mixed>  $rowValues
     * @param  array<string, Attribute>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributeOptions(array $rowValues, array $attributes, int $row): array
    {
        foreach ($attributes as $slug => $attribute) {
            if ($slug === 'kollektsiya' || ! in_array($attribute->type, ['select', 'multiselect'], true)) {
                continue;
            }

            $key = 'attribute.'.$slug;
            if ($attribute->type === 'select') {
                $rowValues[$key] = $this->selectedOptionValues($attribute, [$rowValues[$key] ?? null], $row)[0] ?? null;

                continue;
            }

            $cells = collect($rowValues)->filter(static fn (mixed $cell, string $header): bool => str_starts_with($header, $key.'.'))->all();
            $selected = $this->selectedOptionValues($attribute, $cells, $row);
            $rowValues[$key] = $selected === [] ? null : json_encode(array_values(array_unique($selected)), JSON_THROW_ON_ERROR);
        }

        return $rowValues;
    }

    /**
     * @param  array<string|int, mixed>  $cells
     * @return list<string>
     */
    private function selectedOptionValues(Attribute $attribute, array $cells, int $row): array
    {
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

        return $selected;
    }

    /**
     * @param  array<string, Attribute>  $attributes
     * @param  AttributePayload  $attributePayload
     */
    private function assertRequiredAttributes(Category $category, array $attributes, array $attributePayload, int $row): void
    {
        $requiredAttributeIds = $category->attributes()->wherePivot('is_required', true)->pluck('attributes.id')->all();
        $missingAttributes = collect($attributes)->where('slug', '!=', 'kollektsiya')->whereIn('id', $requiredAttributeIds)
            ->whereNotIn('id', collect($attributePayload)->pluck('attribute_id'));
        if ($missingAttributes->isNotEmpty()) {
            $this->values->rowError($row, 'обязательные характеристики не заполнены: '.$missingAttributes
                ->map(static fn (Attribute $attribute): string => '«'.$attribute->name.'»')->implode(', ').'.');
        }
    }
}
