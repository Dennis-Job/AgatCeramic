<?php

namespace App\Queries;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Typed JSON predicates shared by the public listing and its real-value metadata. */
class PublicCatalogAttributeQuery
{
    /**
     * @param  Builder<Product>  $products
     * @param  array<array-key, mixed>  $filter
     */
    public function apply(Builder $products, Attribute $attribute, array $filter): void
    {
        $products->whereHas('attributeValues', function (Builder $values) use ($attribute, $filter): void {
            $values->where('attribute_id', $attribute->id);
            $pgsql = DB::connection($values->getModel()->getConnectionName())->getDriverName() === 'pgsql';
            $type = $pgsql ? 'jsonb_typeof(product_attribute_values.value::jsonb)' : "json_type(product_attribute_values.value, '$')";
            $text = $pgsql ? "(product_attribute_values.value::jsonb #>> '{}')" : "json_extract(product_attribute_values.value, '$')";
            if (in_array($attribute->type, ['select', 'multiselect', 'boolean'], true)) {
                $selected = $filter['values'] ?? [];
                $selected = is_array($selected) ? array_values(array_filter($selected, is_string(...))) : [];
                $values->where(function (Builder $matches) use ($selected, $attribute, $pgsql, $type, $text): void {
                    foreach ($selected as $value) {
                        if ($attribute->type === 'boolean') {
                            $matches->orWhereRaw($pgsql ? "$type = 'boolean' AND $text = ?" : "$type = ?", [$value]);
                        } elseif ($attribute->type === 'multiselect') {
                            $matches->orWhereRaw($pgsql
                                ? "EXISTS (SELECT 1 FROM jsonb_array_elements(CASE WHEN $type = 'array' THEN product_attribute_values.value::jsonb ELSE '[]'::jsonb END) AS item WHERE jsonb_typeof(item) = 'string' AND item #>> '{}' = ?)"
                                : "EXISTS (SELECT 1 FROM json_each(CASE WHEN $type = 'array' THEN product_attribute_values.value ELSE '[]' END) AS item WHERE item.type = 'text' AND item.value = ?)", [$value]);
                        } else {
                            $matches->orWhereRaw("$type = ? AND $text = ?", [$pgsql ? 'string' : 'text', $value]);
                        }
                    }
                });

                return;
            }
            if (in_array($attribute->type, ['integer', 'decimal'], true)) {
                // CASE guards casts on PostgreSQL and avoids interpreting historical junk as zero on SQLite.
                $numeric = $pgsql
                    ? "CASE WHEN $type IN ('number', 'string') AND $text ~ '^-?[0-9]+([.][0-9]+)?$' THEN CAST($text AS NUMERIC) END"
                    : "CASE WHEN $type IN ('integer', 'real') OR ($type = 'text' AND CASE WHEN json_valid($text) THEN json_type($text) END IN ('integer', 'real')) THEN CAST($text AS NUMERIC) END";
                foreach (['min' => '>=', 'max' => '<='] as $key => $operator) {
                    if (is_string($filter[$key] ?? null)) {
                        $values->whereRaw("($numeric) $operator CAST(? AS NUMERIC)", [$filter[$key]]);
                    }
                }

                return;
            }
            $values->whereRaw("$type = ?", [$pgsql ? 'string' : 'text']);
            if ($attribute->type === 'date') {
                foreach (['min' => '>=', 'max' => '<='] as $key => $operator) {
                    if (is_string($filter[$key] ?? null)) {
                        $values->whereRaw("$text $operator ?", [$filter[$key]]);
                    }
                }
            } elseif (is_string($filter['text'] ?? null)) {
                $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filter['text']).'%';
                $values->whereRaw("LOWER($text) LIKE LOWER(?) ESCAPE '!'", [$needle]);
            }
        });
    }

    /**
     * @param  Builder<Product>  $products
     * @param  Collection<int, Attribute>  $attributes
     * @return array<int, list<array{value: string, label: string}>>
     */
    public function options(Builder $products, Collection $attributes): array
    {
        $actual = [];
        foreach (['select', 'multiselect', 'boolean'] as $type) {
            $ids = array_values($attributes->where('type', $type)->modelKeys());
            if ($ids !== []) {
                $actual += $this->actualValues($products, $ids, $type);
            }
        }
        $result = [];
        foreach ($attributes as $attribute) {
            $result[$attribute->id] = $this->labelOptions($attribute, $actual[$attribute->id] ?? []);
        }

        return $result;
    }

    /**
     * @param  Builder<Product>  $products
     * @param  list<int|string>  $ids
     * @return array<int, list<string>>
     */
    private function actualValues(Builder $products, array $ids, string $attributeType): array
    {
        $values = ProductAttributeValue::query()->whereIn('attribute_id', $ids)->whereIn('product_id', (clone $products)->select('products.id'));
        $pgsql = DB::connection($values->getModel()->getConnectionName())->getDriverName() === 'pgsql';
        $type = $pgsql ? 'jsonb_typeof(product_attribute_values.value::jsonb)' : "json_type(product_attribute_values.value, '$')";
        $text = $pgsql ? "(product_attribute_values.value::jsonb #>> '{}')" : "json_extract(product_attribute_values.value, '$')";
        if ($attributeType === 'multiselect') {
            $values->crossJoin(DB::raw($pgsql
                ? "LATERAL jsonb_array_elements(CASE WHEN $type = 'array' THEN product_attribute_values.value::jsonb ELSE '[]'::jsonb END) AS item"
                : "json_each(CASE WHEN json_type(product_attribute_values.value, '$') = 'array' THEN product_attribute_values.value ELSE '[]' END) AS item"));
            $values->whereRaw($pgsql ? "jsonb_typeof(item) = 'string'" : "item.type = 'text'");
            $text = $pgsql ? "item #>> '{}'" : 'item.value';
        } elseif ($attributeType === 'boolean') {
            $values->whereRaw($pgsql ? "$type = 'boolean'" : "$type IN ('true', 'false')");
            if (! $pgsql) {
                $text = "CASE WHEN $type = 'true' THEN 'true' ELSE 'false' END";
            }
        } else {
            $values->whereRaw("$type = ?", [$pgsql ? 'string' : 'text']);
        }
        // Only distinct option values cross the DB boundary, never product rows or entire JSON arrays.
        $actual = [];
        $rows = $values->selectRaw("product_attribute_values.attribute_id, $text AS option_value")->distinct()->toBase()->get();
        foreach ($rows as $row) {
            if (is_string($row->option_value) && trim($row->option_value) !== '' && (is_int($row->attribute_id) || is_string($row->attribute_id))) {
                $actual[(int) $row->attribute_id][] = $row->option_value;
            }
        }

        return $actual;
    }

    /**
     * @param  list<string>  $actual
     * @return list<array{value: string, label: string}>
     */
    private function labelOptions(Attribute $attribute, array $actual): array
    {
        $result = [];
        if ($attribute->type === 'boolean') {
            foreach (['true' => 'Да', 'false' => 'Нет'] as $value => $label) {
                if (in_array($value, $actual, true)) {
                    $result[] = ['value' => $value, 'label' => $label];
                }
            }
        } else {
            foreach ($attribute->options as $option) {
                if (in_array($option->value, $actual, true)) {
                    $result[] = ['value' => $option->value, 'label' => $option->label];
                    $actual = array_values(array_diff($actual, [$option->value]));
                }
            }
            sort($actual, SORT_STRING);
            foreach ($actual as $value) {
                $result[] = ['value' => $value, 'label' => $value];
            }
        }

        return $result;
    }
}
