<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /** @return array<string, mixed> */
    #[\Override]
    public function definition(): array
    {
        $product = Product::factory();

        return [
            'order_id' => Order::factory(),
            'product_id' => $product,
            'product_name' => 'Товар',
            'product_sku' => '1000001',
            'unit_price' => '1000.00',
            'quantity' => 1,
            'line_total' => '1000.00',
        ];
    }
}
