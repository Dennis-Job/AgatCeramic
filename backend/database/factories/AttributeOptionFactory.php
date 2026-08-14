<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AttributeOption> */
class AttributeOptionFactory extends Factory
{
    protected $model = AttributeOption::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = fake()->unique()->word();

        return ['attribute_id' => Attribute::factory(), 'value' => Str::slug($label), 'label' => $label, 'sort_order' => fake()->numberBetween(0, 100)];
    }
}
