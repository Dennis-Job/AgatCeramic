<?php

namespace Database\Factories;

use App\Models\AttributeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AttributeGroup> */
class AttributeGroupFactory extends Factory
{
    protected $model = AttributeGroup::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['name' => $name, 'slug' => Str::slug($name), 'description' => fake()->optional()->sentence(), 'sort_order' => fake()->numberBetween(0, 100)];
    }
}
