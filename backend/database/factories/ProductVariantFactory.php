<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 100, 100000);

        return [
            'product_id' => Product::factory(),
            'name' => fake()->bothify('Variant ##'),
            'sku' => fake()->unique()->bothify('SKU-########'),
            'article_number' => fake()->boolean(70) ? fake()->unique()->bothify('ART-########') : null,
            'barcode' => fake()->boolean(70) ? fake()->unique()->ean13() : null,
            'unit' => fake()->randomElement(ProductUnit::cases())->value,
            'price' => $price,
            'old_price' => fake()->boolean(30) ? $price + fake()->randomFloat(2, 1, 1000) : null,
            'stock_quantity' => fake()->numberBetween(0, 500),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
