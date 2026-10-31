<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ProductCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductCollection> */
class ProductCollectionFactory extends Factory
{
    protected $model = ProductCollection::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return ['brand_id' => Brand::factory(), 'name' => $name, 'slug' => Str::slug($name), 'is_active' => true];
    }
}
