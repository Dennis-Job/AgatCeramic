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
        [$headers, $rows] = $this->read($path);
        $attributes = $this->attributesFor($headers);

        return DB::transaction(function () use ($actor, $headers, $rows, $attributes): array {
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
                if ($product !== null && isset($seenProductIds[$product->id])) {
                    $this->rowError($rowNumber, "товар уже указан в строке {$seenProductIds[$product->id]}.");
                }
                if ($product !== null) {
                    $seenProductIds[$product->id] = $rowNumber;
                }

                $category = $this->resolveCategory($values, $rowNumber);
                $brand = $this->resolveBrand($values, $rowNumber);
                $payload = $this->productPayload($values, $category, $brand, $product, $rowNumber);
                $attributePayload = $this->attributePayload($values, $attributes, $rowNumber);
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

    /** @return array{list<string>, list<array{row: int, values: list<mixed>}>} */
    private function read(string $path): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);
            $headers = [];
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = $row->toArray();
                    if ($index === 1) {
                        $headers = $this->headers($values);

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
                break;
            }

            if ($headers === []) {
                throw ValidationException::withMessages(['file' => ['XLSX-файл не содержит строки заголовков.']]);
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['file' => ['XLSX-файл не содержит товаров для импорта.']]);
            }

            return [$headers, $rows];
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
    private function headers(array $values): array
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
        $missing = array_values(array_diff(ProductWorkbookSchema::REQUIRED_IMPORT_HEADERS, $headers));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => ['В XLSX отсутствуют обязательные столбцы: '.implode(', ', $missing).'.'],
            ]);
        }

        return $headers;
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
        $id = $this->nullableInteger($values['id'], $row, 'id');
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
    private function attributePayload(array $values, array $attributes, int $row): array
    {
        $payload = [];
        foreach ($attributes as $slug => $attribute) {
            $cell = $values['attribute.'.$slug] ?? null;
            if ($cell === null || (is_string($cell) && trim($cell) === '')) {
                continue;
            }
            $value = match ($attribute->type) {
                'string', 'text', 'select' => $this->requiredString($cell, $row, 'attribute.'.$slug),
                'integer' => $this->requiredInteger($cell, $row, 'attribute.'.$slug),
                'decimal' => $this->decimal($cell, $row, 'attribute.'.$slug),
                'boolean' => $this->boolean($cell, $row, 'attribute.'.$slug),
                'multiselect' => $this->multiselect($cell, $row, 'attribute.'.$slug),
                'date' => $this->date($cell, $row, 'attribute.'.$slug),
                default => $this->rowError($row, "тип характеристики {$slug} не поддерживается."),
            };
            $payload[] = ['attribute_id' => $attribute->id, 'value' => $value];
        }

        return $payload;
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
        if ($value === 1 || $value === 1.0 || $value === '1' || (is_string($value) && mb_strtolower(trim($value)) === 'true')) {
            return true;
        }
        if ($value === 0 || $value === 0.0 || $value === '0' || (is_string($value) && mb_strtolower(trim($value)) === 'false')) {
            return false;
        }
        $this->rowError($row, "столбец {$field} должен содержать true/false или 1/0.");
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
