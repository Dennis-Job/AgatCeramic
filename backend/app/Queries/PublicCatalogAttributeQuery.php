<?php

namespace App\Queries;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Support\CatalogAttributeOptionValue;
use Brick\Math\BigDecimal;
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
            ['type' => $type, 'text' => $text] = $this->jsonExpressions($pgsql, $attribute->type);
            if (isset($filter['values'])) {
                $selected = $filter['values'];
                $selected = is_array($selected) ? array_values(array_filter($selected, is_string(...))) : [];
                $values->where(function (Builder $matches) use ($selected, $attribute, $pgsql, $type, $text): void {
                    foreach ($selected as $value) {
                        if (in_array($attribute->type, ['integer', 'decimal'], true)) {
                            $numeric = $this->exactNumericExpression($pgsql, $type, $text);
                            $matches->orWhereRaw("($numeric) = CAST(? AS NUMERIC)", [$value]);
                        } elseif ($attribute->type === 'boolean') {
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
        foreach (Attribute::TYPES as $type) {
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
        ['type' => $type, 'text' => $text] = $this->jsonExpressions($pgsql, $attributeType);
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
        } elseif (in_array($attributeType, ['integer', 'decimal'], true)) {
            $values->whereRaw($pgsql ? "$type IN ('number', 'string')" : "$type IN ('integer', 'real', 'text')");
            // Preserve the JSON numeric literal; SQLite json_extract would round it through a float.
            if (! $pgsql) {
                $text = "CASE WHEN $type IN ('integer', 'real') THEN CAST(product_attribute_values.value AS TEXT) ELSE $text END";
            }
        } else {
            $values->whereRaw("$type = ?", [$pgsql ? 'string' : 'text']);
        }
        // Only distinct option values cross the DB boundary, never product rows or entire JSON arrays.
        $actual = [];
        $rows = $values->selectRaw("product_attribute_values.attribute_id, $text AS option_value")->distinct()->toBase()->get();
        foreach ($rows as $row) {
            if (is_string($row->option_value) && (is_int($row->attribute_id) || is_string($row->attribute_id))) {
                $value = CatalogAttributeOptionValue::normalize($attributeType, $row->option_value);
                if ($value !== null) {
                    $actual[(int) $row->attribute_id][] = $value;
                }
            }
        }

        return array_map(fn (array $options): array => array_values(array_unique($options, SORT_STRING)), $actual);
    }

    /**
     * @param  literal-string  $type
     * @param  literal-string  $text
     * @return literal-string
     */
    private function exactNumericExpression(bool $pgsql, string $type, string $text): string
    {
        return $pgsql
            ? "CASE WHEN $type IN ('number', 'string') AND LENGTH($text) <= 1024 AND $text ~ '^-?(0|[1-9][0-9]*)([.][0-9]+)?([eE][+-]?[0-9]{1,3})?$' THEN CAST($text AS NUMERIC) END"
            : "CASE WHEN $type IN ('integer', 'real') OR ($type = 'text' AND LENGTH($text) <= 1024 AND $text NOT GLOB '*[^0-9.eE+-]*' AND CASE WHEN json_valid($text) THEN json_type($text) END IN ('integer', 'real')) THEN CAST($text AS NUMERIC) END";
    }

    /** @return array{type: literal-string, text: literal-string} */
    private function jsonExpressions(bool $pgsql, string $attributeType): array
    {
        $value = 'product_attribute_values.value';
        $guardedScalar = in_array($attributeType, ['string', 'text', 'integer', 'decimal', 'date'], true);
        if ($pgsql) {
            // Guard the cast itself: historical json may contain escapes jsonb cannot represent.
            $json = $guardedScalar
                ? "CASE WHEN pg_input_is_valid($value::text, 'jsonb') THEN $value::jsonb END"
                : "$value::jsonb";

            return ['type' => "jsonb_typeof($json)", 'text' => "($json #>> '{}')"];
        }
        $text = "json_extract($value, '$')";
        if ($guardedScalar) {
            // SQLite truncates strings at NUL; remove escaped backslash pairs before detecting it.
            $text = "CASE WHEN INSTR(REPLACE(LOWER($value), '\\\\', ''), '\\u0000') = 0 THEN $text END";
        }

        return ['type' => "json_type($value, '$')", 'text' => $text];
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
            if (in_array($attribute->type, ['integer', 'decimal'], true)) {
                usort($actual, fn (string $left, string $right): int => BigDecimal::of($left)->compareTo($right));
            } else {
                sort($actual, SORT_STRING);
            }
            foreach ($actual as $value) {
                $result[] = ['value' => $value, 'label' => $value];
            }
        }

        return $result;
    }
}
