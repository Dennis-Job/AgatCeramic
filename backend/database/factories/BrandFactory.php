<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Brand> */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        // Faker uniqueness resets between applications while integration rows can persist.
        $name = fake()->company().' '.Str::uuid();

        return ['name' => $name, 'slug' => Str::slug($name), 'description' => fake()->optional()->paragraph(), 'country_code' => fake()->optional()->randomElement(['IT', 'ES', 'RU']), 'is_active' => true];
    }
}
