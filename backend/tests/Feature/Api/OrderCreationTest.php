<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_creates_an_order_with_current_immutable_product_snapshots(): void
    {
        $cart = Cart::factory()->create();
        $firstProduct = Product::factory()->create(['name' => 'Белый мрамор', 'sku' => '11000001', 'price' => '1200.00', 'stock_quantity' => 10]);
        $secondProduct = Product::factory()->create(['name' => 'Клей для плитки', 'sku' => '12000001', 'price' => '500.00', 'stock_quantity' => 4]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $firstProduct->id, 'quantity' => 2]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $secondProduct->id, 'quantity' => 3]);

        $firstProduct->update(['price' => '1250.00']);
        $response = $this->withHeader('X-Cart-Token', $cart->token)
            ->withHeader('Idempotency-Key', 'order-create-immutable-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.payment_status', 'not_paid')
            ->assertJsonPath('data.total_amount', '4000.00')
            ->assertJsonPath('data.items.0.product_name', 'Белый мрамор')
            ->assertJsonPath('data.items.0.unit_price', '1250.00')
            ->assertJsonPath('data.items.0.line_total', '2500.00')
            ->assertJsonPath('data.items.1.product_name', 'Клей для плитки')
            ->assertJsonPath('data.items.1.line_total', '1500.00')
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.customer_email')
            ->assertJsonMissingPath('data.delivery_address');

        $number = $response->json('data.order_number');
        $this->assertMatchesRegularExpression('/^AC-\d{8}-[A-F0-9]{10}$/', $number);
        $this->assertDatabaseHas('orders', [
            'order_number' => $number,
            'customer_name' => 'Иван Петров',
            'customer_phone' => '+7 (999) 123-45-67',
            'total_amount' => '4000.00',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $firstProduct->id,
            'product_name' => 'Белый мрамор',
            'product_sku' => '11000001',
            'unit_price' => '1250.00',
            'quantity' => 2,
            'line_total' => '2500.00',
        ]);
        $this->assertDatabaseCount('cart_items', 0);

        $firstProduct->update(['name' => 'Новое название', 'price' => '1.00']);
        $order = Order::query()->where('order_number', $number)->with('items')->sole();
        $this->assertSame(PaymentStatus::NotPaid, $order->payment_status);
        $this->assertSame('Белый мрамор', $order->items->firstWhere('product_id', $firstProduct->id)->product_name);
        $this->assertSame('1250.00', $order->items->firstWhere('product_id', $firstProduct->id)->unit_price);
    }

    public function test_checkout_rejects_empty_or_unavailable_carts_without_creating_an_order(): void
    {
        $emptyCart = Cart::factory()->create();
        $this->withHeader('X-Cart-Token', $emptyCart->token)
            ->withHeader('Idempotency-Key', 'order-create-empty-cart-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.details.cart.0', 'Корзина пуста.');

        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $item = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
        $product->update(['stock_quantity' => 1]);

        $this->withHeader('X-Cart-Token', $cart->token)
            ->withHeader('Idempotency-Key', 'order-create-unavailable-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.details.cart.0', 'Корзина содержит недоступный товар или недостаточное количество.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 2]);
    }

    public function test_checkout_validates_contact_data_and_honeypot_before_using_the_cart(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->withHeader('X-Cart-Token', $cart->token)
            ->withHeader('Idempotency-Key', 'order-create-validation-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload([
                'customer_phone' => '123',
                'website' => 'https://spam.example',
                'total_amount' => '1.00',
                'items' => [['product_id' => $product->id, 'quantity' => 9999]],
            ]))
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['customer_phone', 'website', 'total_amount', 'items']]]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_checkout_replays_the_original_order_for_the_same_idempotency_key(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['price' => '100.00', 'stock_quantity' => 2]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $key = 'order-create-replay-test-0001';

        $first = $this->withHeader('X-Cart-Token', $cart->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertCreated();
        $second = $this->withHeader('X-Cart-Token', $cart->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.order_number'), $second->json('data.order_number'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_rejects_reusing_an_idempotency_key_with_different_data(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 2]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $key = 'order-create-conflict-test-001';

        $this->withHeader('X-Cart-Token', $cart->token)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $this->checkoutPayload())->assertCreated();
        $this->withHeader('X-Cart-Token', $cart->token)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $this->checkoutPayload(['delivery_address' => 'Другой адрес']))
            ->assertConflict();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function checkoutPayload(array $overrides = []): array
    {
        return [
            'customer_name' => 'Иван Петров',
            'customer_phone' => '+7 (999) 123-45-67',
            'customer_email' => 'ivan@example.test',
            'delivery_address' => 'Москва, улица Пример, дом 1, квартира 2',
            'customer_comment' => 'Позвонить перед доставкой',
            ...$overrides,
        ];
    }
}
