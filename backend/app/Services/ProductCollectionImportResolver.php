<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;

final class ProductCollectionImportResolver
{
    public function __construct(private readonly ProductImportValueParser $values) {}

    /**
     * An absent column preserves the assignment; a present empty cell explicitly clears it.
     *
     * @param  array<string, mixed>  $cells
     * @return array{collection_id?: ?int}
     */
    public function resolve(array $cells, Category $category, ?Brand $brand, ?Product $product, int $row): array
    {
        $hasName = array_key_exists('collection_name', $cells);
        $hasLegacy = array_key_exists('attribute.kollektsiya', $cells);
        if (! $hasName && ! $hasLegacy) {
            return [];
        }
        $name = $this->name($cells[$hasName ? 'collection_name' : 'attribute.kollektsiya'], $row);
        if ($hasName && $hasLegacy && $name !== $this->name($cells['attribute.kollektsiya'], $row)) {
            $this->values->rowError($row, 'столбцы коллекции содержат разные значения. Оставьте одно согласованное значение.');
        }
        if ($name === null) {
            return ['collection_id' => null];
        }
        if (! $category->uses_collections || $brand === null) {
            $this->values->rowError($row, 'для коллекции нужны разрешённая категория и выбранный бренд.');
        }
        $collection = ProductCollection::query()->where('brand_id', $brand->id)->where('name', $name)->first();
        if ($collection === null || (! $collection->is_active && $product?->collection_id !== $collection->id)) {
            $this->values->rowError($row, 'коллекция «'.$name.'» не найдена среди доступных коллекций выбранного бренда. Создайте её в справочнике коллекций.');
        }

        return ['collection_id' => $collection->id];
    }

    private function name(mixed $cell, int $row): ?string
    {
        if ($cell !== null && ! is_string($cell)) {
            $this->values->rowError($row, 'название коллекции должно быть текстом.');
        }

        return $this->values->nullableString($cell);
    }
}
