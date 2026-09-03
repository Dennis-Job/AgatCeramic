<?php

namespace App\Services;

use App\Enums\ProductUnit;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductWorkbookSchema;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;
use Throwable;

class ProductImportService
{
    public const MAX_ROWS = 5000;

    public function __construct(
        private readonly ProductManagementService $productService,
        private readonly ProductAttributeValueManagementService $attributeValueService,
    ) {}

    /** @return array{created: int, updated: int, processed: int} */
    public function import(User $actor, string $path): array
    {
        [$headers, $rows, $seoProducts, $localized] = $this->read($path);
        $attributes = $this->attributesFor($headers);

        return DB::transaction(function () use ($actor, $headers, $rows, $attributes, $seoProducts, $localized): array {
            $created = 0;
            $updated = 0;
            $seenProductIds = [];

            foreach ($rows as $entry) {
                $rowNumber = $entry['row'];
                $values = array_combine($headers, $entry['values']);
                if ($values === false) {
                    $this->rowError($rowNumber, 'структура строки не совпадает с заголовками.');
                }

                $product = $this->resolveProduct($values, $rowNumber);
                if ($localized) {
                    $values = $this->localizedValues($values, $seoProducts, $product, $rowNumber);
                }
                if ($product !== null && isset($seenProductIds[$product->id])) {
                    $this->rowError($rowNumber, "товар уже указан в строке {$seenProductIds[$product->id]}.");
                }
                if ($product !== null) {
                    $seenProductIds[$product->id] = $rowNumber;
                }

                $category = $this->resolveCategory($values, $rowNumber);
                $brand = $this->resolveBrand($values, $rowNumber);
                $payload = $this->productPayload($values, $category, $brand, $product, $rowNumber);
                $attributePayload = $this->attributePayload($values, $attributes, $rowNumber, $localized);
                $activate = $payload['is_active'];
                $payload['is_active'] = false;

                if ($product === null) {
                    $product = $this->productService->create($actor, $payload);
                    $created++;
                } else {
                    if ($product->category_id !== $category->id) {
                        $this->productService->update($actor, $product, ['is_active' => false]);
                        $this->attributeValueService->replace($actor, $product, []);
                    }
                    $product = $this->productService->update($actor, $product, $payload);
                    $updated++;
                }

                $product = $this->attributeValueService->replace($actor, $product, $attributePayload);
                if ($activate) {
                    $this->productService->update($actor, $product, ['is_active' => true]);
                }
            }

            return ['created' => $created, 'updated' => $updated, 'processed' => $created + $updated];
        });
    }

    /** @return array{list<string>, list<array{row: int, values: list<mixed>}>, array<string, array<string, mixed>>, bool} */
    private function read(string $path): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);
            $headers = [];
            $rows = [];
            $seoProducts = [];
            $seoAttributes = [];
            $firstSheet = true;

            foreach ($reader->getSheetIterator() as $sheet) {
                if (! $firstSheet) {
                    if (in_array($sheet->getName(), ['SEO товаров', 'SEO характеристик'], true)) {
                        $seoRows = $this->readSeoSheet($sheet);
                        if ($sheet->getName() === 'SEO товаров') {
                            foreach ($seoRows as $seoRow) {
                                $sku = $this->nullableString($seoRow['SKU'] ?? null);
                                if ($sku === null || isset($seoProducts[$sku])) {
                                    throw ValidationException::withMessages(['file' => ['На листе SEO товаров SKU должны быть заполнены и не повторяться.']]);
                                }
                                $seoProducts[$sku] = $seoRow;
                            }
                        } else {
                            $seoAttributes = $seoRows;
                        }
                    }

                    continue;
                }
                $firstSheet = false;
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = $row->toArray();
                    if ($index === 1) {
                        $headers = $this->headers($values, false);

                        continue;
                    }
                    if ($this->emptyRow($values)) {
                        continue;
                    }
                    if (count($rows) >= self::MAX_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => ['Один импорт поддерживает не более '.self::MAX_ROWS.' строк.'],
                        ]);
                    }
                    if (count($values) > count($headers) && ! $this->emptyRow(array_slice($values, count($headers)))) {
                        $this->rowError($index, 'найдены значения за пределами заголовков.');
                    }
                    $rows[] = [
                        'row' => $index,
                        'values' => array_pad(array_slice($values, 0, count($headers)), count($headers), null),
                    ];
                }
            }

            if ($headers === []) {
                throw ValidationException::withMessages(['file' => ['XLSX-файл не содержит строки заголовков.']]);
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['file' => ['XLSX-файл не содержит товаров для импорта.']]);
            }

            $localized = in_array('SKU', $headers, true);
            $headers = $localized ? $this->localizedHeaders($headers, $seoAttributes) : $this->headers($headers);

            return [$headers, $rows, $seoProducts, $localized];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => ['Не удалось прочитать XLSX-файл. Проверьте, что файл не повреждён.']]);
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
                // The validation response is more useful than a secondary reader cleanup failure.
            }
        }
    }

    /** @param list<mixed> $values
     * @return list<string>
     */
    private function headers(array $values, bool $requireLegacy = true): array
    {
        $headers = array_map(static function (mixed $value): string {
            if (! is_string($value)) {
                return '';
            }

            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
        }, $values);

        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }
        if ($headers === [] || in_array('', $headers, true)) {
            throw ValidationException::withMessages(['file' => ['Все заголовки XLSX должны быть непустыми текстовыми значениями.']]);
        }
        if (count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['file' => ['Заголовки XLSX не должны повторяться.']]);
        }
        $missing = $requireLegacy ? array_values(array_diff(ProductWorkbookSchema::REQUIRED_IMPORT_HEADERS, $headers)) : [];
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => ['В XLSX отсутствуют обязательные столбцы: '.implode(', ', $missing).'.'],
            ]);
        }

        return $headers;
    }

    /** @return list<array<string, mixed>> */
    private function readSeoSheet(Sheet $sheet): array
    {
        $headers = [];
        $rows = [];
        foreach ($sheet->getRowIterator() as $index => $row) {
            $values = $row->toArray();
            if ($index === 1) {
                $headers = $this->headers($values, false);

                continue;
            }
            if ($this->emptyRow($values)) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => ['На листе '.$sheet->getName().' превышен лимит строк.']]);
            }
            $rows[] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));
        }

        return $rows;
    }

    /** @param list<string> $headers
     * @param  list<array<string, mixed>>  $seoAttributes
     * @return list<string>
     */
    private function localizedHeaders(array $headers, array $seoAttributes): array
    {
        $baseHeaders = array_flip(ProductWorkbookSchema::MANAGER_HEADERS);
        $attributeHeaders = [];
        if ($seoAttributes !== []) {
            foreach ($seoAttributes as $attribute) {
                $name = $this->requiredString($attribute['Название'] ?? null, 1, 'Название характеристики');
                $unit = $this->nullableString($attribute['Единица измерения'] ?? null);
                $header = $name.($unit === null ? '' : ' ('.$unit.')');
                $attributeHeaders[$header][] = $this->requiredString($attribute['URL характеристики (slug)'] ?? null, 1, 'URL характеристики (slug)');
            }
        } else {
            foreach (Attribute::query()->get() as $attribute) {
                $header = $attribute->name.($attribute->unit ? ' ('.$attribute->unit.')' : '');
                $attributeHeaders[$header][] = $attribute->slug;
            }
        }

        $normalized = [];
        foreach ($headers as $header) {
            if (isset($baseHeaders[$header])) {
                $normalized[] = $baseHeaders[$header];

                continue;
            }
            $matches = $attributeHeaders[$header] ?? [];
            if (count($matches) !== 1) {
                throw ValidationException::withMessages(['file' => ["Столбец «{$header}» не найден или соответствует нескольким характеристикам. Проверьте лист SEO характеристик."]]);
            }
            $normalized[] = 'attribute.'.$matches[0];
        }
        if (count($normalized) !== count(array_unique($normalized))) {
            throw ValidationException::withMessages(['file' => ['Несколько столбцов ссылаются на одну характеристику.']]);
        }
        $required = ['sku', 'name', 'category_name', 'brand_name', 'unit', 'price', 'old_price', 'stock_quantity', 'is_active', 'is_on_sale'];
        $missing = array_diff($required, $normalized);
        if ($missing !== []) {
            $labels = array_map(static fn (string $field): string => ProductWorkbookSchema::MANAGER_HEADERS[$field], $missing);
            throw ValidationException::withMessages(['file' => ['В XLSX отсутствуют обязательные столбцы: '.implode(', ', $labels).'.']]);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $values
     * @param  array<string, array<string, mixed>>  $seoProducts
     * @return array<string, mixed>
     */
    private function localizedValues(array $values, array $seoProducts, ?Product $product, int $row): array
    {
        $sku = $this->nullableString($values['sku'] ?? null);
        $seo = $sku === null ? [] : ($seoProducts[$sku] ?? []);
        if ($product === null && ($sku === null || $this->nullableString($seo['URL товара (slug)'] ?? null) === null)) {
            $this->rowError($row, 'товар с указанным SKU не найден. Для нового товара заполните временный SKU и URL товара на соответствующей строке листа SEO товаров. SKU существующего товара нельзя изменять.');
        }
        $values['slug'] = $this->nullableString($seo['URL товара (slug)'] ?? null) ?? $product?->slug;
        $units = array_flip(ProductWorkbookSchema::UNIT_LABELS);
        $values['unit'] = $units[$this->nullableString($values['unit']) ?? ''] ?? $values['unit'];
        $values['category_id'] = null;
        $values['brand_id'] = null;
        foreach (['category' => 'Категория', 'brand' => 'Бренд'] as $field => $label) {
            $name = $this->nullableString($values[$field.'_name']);
            if ($field === 'brand' && $name === null) {
                $values['brand_slug'] = null;

                continue;
            }
            $seoSlugHeader = $field === 'category' ? 'URL категории (slug)' : 'URL бренда (slug)';
            $slug = $this->nullableString($seo[$seoSlugHeader] ?? null);
            if ($name !== null && $name === $this->nullableString($seo[$label] ?? null) && $slug !== null) {
                $values[$field.'_slug'] = $slug;

                continue;
            }
            $model = $field === 'category' ? Category::class : Brand::class;
            $matches = $name === null ? collect() : $model::query()->where('name', $name)->limit(2)->get();
            if ($matches->count() !== 1) {
                $this->rowError($row, "{$label} «{$name}» не найдена или название неоднозначно. Укажите точное уникальное название либо название и URL на листе SEO товаров.");
            }
            $values[$field.'_slug'] = $matches->first()->slug;
        }

        return $values;
    }

    /** @param list<mixed> $values */
    private function emptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $headers
     * @return array<string, Attribute>
     */
    private function attributesFor(array $headers): array
    {
        $slugs = collect($headers)
            ->filter(static fn (string $header): bool => str_starts_with($header, 'attribute.'))
            ->map(static fn (string $header): string => substr($header, 10))
            ->values();
        if ($slugs->contains('')) {
            throw ValidationException::withMessages(['file' => ['Столбец характеристики должен иметь формат attribute.<slug>.']]);
        }

        $attributes = Attribute::query()->with('options')->whereIn('slug', $slugs)->get()->keyBy('slug');
        $missing = $slugs->diff($attributes->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file' => ['Неизвестные характеристики: '.$missing->implode(', ').'.'],
            ]);
        }

        return $attributes->all();
    }

    /** @param array<string, mixed> $values */
    private function resolveProduct(array $values, int $row): ?Product
    {
        $id = $this->nullableInteger($values['id'] ?? null, $row, 'id');
        $sku = $this->nullableString($values['sku']);
        $byId = $id === null ? null : Product::query()->find($id);
        $bySku = $sku === null ? null : Product::query()->where('sku', $sku)->first();

        if ($byId !== null && $bySku !== null && $byId->id !== $bySku->id) {
            $this->rowError($row, 'id и sku относятся к разным товарам.');
        }
        if ($byId !== null && $sku !== null && $bySku === null) {
            $this->rowError($row, 'sku не совпадает с товаром, найденным по id.');
        }

        return $byId ?? $bySku;
    }

    /** @param array<string, mixed> $values */
    private function resolveCategory(array $values, int $row): Category
    {
        $id = $this->nullableInteger($values['category_id'], $row, 'category_id');
        $slug = $this->nullableString($values['category_slug']);
        $byId = $id === null ? null : Category::query()->find($id);
        $bySlug = $slug === null ? null : Category::query()->where('slug', $slug)->first();

        if ($byId !== null && $bySlug !== null && $byId->id !== $bySlug->id) {
            $this->rowError($row, 'category_id и category_slug относятся к разным категориям.');
        }
        if ($byId !== null && $slug !== null && $bySlug === null) {
            $this->rowError($row, 'category_slug не совпадает с категорией, найденной по category_id.');
        }
        $category = $bySlug ?? $byId;
        if ($category === null) {
            $this->rowError($row, 'категория не найдена по category_slug или category_id.');
        }

        return $category;
    }

    /** @param array<string, mixed> $values */
    private function resolveBrand(array $values, int $row): ?Brand
    {
        $id = $this->nullableInteger($values['brand_id'], $row, 'brand_id');
        $slug = $this->nullableString($values['brand_slug']);
        if ($id === null && $slug === null) {
            return null;
        }
        $byId = $id === null ? null : Brand::query()->find($id);
        $bySlug = $slug === null ? null : Brand::query()->where('slug', $slug)->first();
        if ($byId !== null && $bySlug !== null && $byId->id !== $bySlug->id) {
            $this->rowError($row, 'brand_id и brand_slug относятся к разным брендам.');
        }
        if ($byId !== null && $slug !== null && $bySlug === null) {
            $this->rowError($row, 'brand_slug не совпадает с брендом, найденным по brand_id.');
        }
        $brand = $bySlug ?? $byId;
        if ($brand === null) {
            $this->rowError($row, 'бренд не найден по brand_slug или brand_id.');
        }

        return $brand;
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function productPayload(array $values, Category $category, ?Brand $brand, ?Product $product, int $row): array
    {
        $payload = ['category_id' => $category->id, 'brand_id' => $brand?->id];
        foreach (ProductWorkbookSchema::WRITABLE_PRODUCT_FIELDS as $field) {
            $payload[$field] = match ($field) {
                'description', 'article_number', 'barcode', 'old_price' => $this->nullableString($values[$field] ?? null),
                'name', 'slug', 'unit', 'price' => $this->requiredString($values[$field] ?? null, $row, $field),
                'stock_quantity' => $this->nullableInteger($values[$field] ?? null, $row, $field),
                'is_active', 'is_on_sale' => $this->boolean($values[$field] ?? null, $row, $field),
            };
        }

        $uniqueSlug = Rule::unique('products', 'slug');
        $uniqueArticle = Rule::unique('products', 'article_number');
        $uniqueBarcode = Rule::unique('products', 'barcode');
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
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
            'is_on_sale' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            $this->rowError($row, $validator->errors()->first());
        }

        return $validator->validated();
    }

    /** @param array<string, mixed> $values
     * @param  array<string, Attribute>  $attributes
     * @return list<array{attribute_id: int, value: mixed}>
     */
    private function attributePayload(array $values, array $attributes, int $row, bool $localized = false): array
    {
        $payload = [];
        foreach ($attributes as $slug => $attribute) {
            $cell = $values['attribute.'.$slug] ?? null;
            if ($cell === null || (is_string($cell) && trim($cell) === '')) {
                continue;
            }
            $value = match ($attribute->type) {
                'string', 'text' => $this->requiredString($cell, $row, 'attribute.'.$slug),
                'select' => $localized ? $this->optionValue($attribute, $cell, $row) : $this->requiredString($cell, $row, 'attribute.'.$slug),
                'integer' => $this->requiredInteger($cell, $row, 'attribute.'.$slug),
                'decimal' => $this->decimal($cell, $row, 'attribute.'.$slug),
                'boolean' => $this->boolean($cell, $row, 'attribute.'.$slug),
                'multiselect' => $localized ? $this->optionValues($attribute, $cell, $row) : $this->multiselect($cell, $row, 'attribute.'.$slug),
                'date' => $this->date($cell, $row, 'attribute.'.$slug),
                default => $this->rowError($row, "тип характеристики {$slug} не поддерживается."),
            };
            $payload[] = ['attribute_id' => $attribute->id, 'value' => $value];
        }

        return $payload;
    }

    private function optionValue(Attribute $attribute, mixed $cell, int $row): string
    {
        $label = $this->requiredString($cell, $row, $attribute->name);
        $matches = $attribute->options->filter(static fn ($option): bool => $option->label === $label);
        if ($matches->count() !== 1) {
            $this->rowError($row, "значение «{$label}» характеристики «{$attribute->name}» не найдено или неоднозначно.");
        }

        return $matches->first()->value;
    }

    /** @return list<string> */
    private function optionValues(Attribute $attribute, mixed $cell, int $row): array
    {
        $text = $this->requiredString($cell, $row, $attribute->name);

        return array_map(fn (?string $label): string => $this->optionValue($attribute, trim($label ?? ''), $row), str_getcsv($text, ';', '"', ''));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function requiredString(mixed $value, int $row, string $field): string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            $this->rowError($row, "столбец {$field} должен быть заполнен.");
        }

        return $value;
    }

    private function nullableInteger(mixed $value, int $row, string $field): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            return (int) trim($value);
        }
        $this->rowError($row, "столбец {$field} должен содержать целое число.");
    }

    private function requiredInteger(mixed $value, int $row, string $field): int
    {
        $value = $this->nullableInteger($value, $row, $field);
        if ($value === null) {
            $this->rowError($row, "столбец {$field} должен быть заполнен.");
        }

        return $value;
    }

    private function decimal(mixed $value, int $row, string $field): float
    {
        if (! is_numeric($value)) {
            $this->rowError($row, "столбец {$field} должен содержать число.");
        }

        return (float) $value;
    }

    private function boolean(mixed $value, int $row, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === 1.0 || $value === '1' || (is_string($value) && in_array(mb_strtolower(trim($value)), ['true', 'да'], true))) {
            return true;
        }
        if ($value === 0 || $value === 0.0 || $value === '0' || (is_string($value) && in_array(mb_strtolower(trim($value)), ['false', 'нет'], true))) {
            return false;
        }
        $this->rowError($row, "столбец {$field} должен содержать Да/Нет, true/false или 1/0.");
    }

    /** @return list<string> */
    private function multiselect(mixed $value, int $row, string $field): array
    {
        if (! is_string($value)) {
            $this->rowError($row, "столбец {$field} должен содержать JSON-массив строк.");
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->rowError($row, "столбец {$field} должен содержать корректный JSON-массив строк.");
        }
        if (! is_array($decoded) || ! array_is_list($decoded) || collect($decoded)->contains(fn (mixed $item): bool => ! is_string($item))) {
            $this->rowError($row, "столбец {$field} должен содержать JSON-массив строк.");
        }

        return $decoded;
    }

    private function date(mixed $value, int $row, string $field): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $this->requiredString($value, $row, $field);
    }

    private function rowError(int $row, string $message): never
    {
        throw ValidationException::withMessages(['file' => ["Строка {$row}: {$message}"]]);
    }
}
