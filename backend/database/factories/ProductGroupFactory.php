<?php

namespace Database\Factories;

use App\Models\ProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductGroup> */
class ProductGroupFactory extends Factory
{
    protected $model = ProductGroup::class;

    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'code' => fake()->unique()->bothify('GROUP-########')];
    }
}
