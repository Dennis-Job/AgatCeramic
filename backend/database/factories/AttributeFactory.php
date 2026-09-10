<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Attribute> */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        $name = fake()->unique()->sentence(2);

        return ['name' => $name, 'slug' => Str::slug($name), 'type' => 'string', 'unit' => null, 'is_filterable' => false, 'is_required' => false, 'is_visible_on_product_page' => true, 'sort_order' => fake()->numberBetween(0, 100)];
    }
}
