<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->optional()->paragraph(),
            'sku' => fake()->unique()->bothify('SKU-########'),
            'article_number' => fake()->boolean(70) ? fake()->unique()->bothify('ART-########') : null,
            'barcode' => fake()->boolean(70) ? fake()->unique()->ean13() : null,
            'unit' => fake()->randomElement(ProductUnit::cases())->value,
            'price' => fake()->randomFloat(2, 100, 100000),
            'old_price' => null,
            'stock_quantity' => fake()->numberBetween(0, 500),
            'is_active' => true,
            'is_on_sale' => false,
        ];
    }
}
