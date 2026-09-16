<?php

namespace App\Services;

use App\Models\Attribute;

final class ProductGroupAxisChoices
{
    /** @param iterable<Attribute> $attributes
     * @return array<int, string>
     */
    public function forAttributes(iterable $attributes): array
    {
        $items = is_array($attributes) ? $attributes : iterator_to_array($attributes);
        $counts = [];
        foreach ($items as $attribute) {
            $key = mb_strtolower(trim((string) $attribute->name));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        $choices = [];
        foreach ($items as $attribute) {
            $key = mb_strtolower(trim((string) $attribute->name));
            $name = (string) $attribute->name;
            $choices[(int) $attribute->id] = $counts[$key] > 1 ? "{$name} (ID {$attribute->id})" : $name;
        }

        return $choices;
    }
}
