<?php

namespace App\Http\Resources;

use App\Models\Attribute;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Request;

/** @extends ApiResource<ProductAttributeValue> */
class PublicProductAttributeResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        $attribute = $this->attribute;
        if ($attribute === null) {
            throw new \LogicException('Public product value must have an attribute.');
        }

        return [
            'id' => $attribute->id, 'slug' => $attribute->slug, 'name' => $attribute->name,
            'type' => $attribute->type, 'unit' => $attribute->unit, 'value' => $this->value,
            'display_value' => $this->displayValue($attribute, $this->value),
        ];
    }

    private function displayValue(Attribute $attribute, mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $item): string => $this->displayValue($attribute, $item), $value));
        }
        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }
        if (! is_scalar($value)) {
            return '';
        }
        $text = (string) $value;
        if (in_array($attribute->type, ['select', 'multiselect'], true)) {
            return $attribute->options->firstWhere('value', $text)->label ?? $text;
        }

        return in_array($attribute->type, ['integer', 'decimal'], true) ? str_replace('.', ',', $text) : $text;
    }
}
