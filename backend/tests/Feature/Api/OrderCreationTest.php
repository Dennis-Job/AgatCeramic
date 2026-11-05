<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Concerns\CreatesGuestCarts;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use CreatesGuestCarts;
    use RefreshDatabase;

    public function test_guest_checkout_creates_an_order_with_current_immutable_product_snapshots(): void
    {
        config(['cart.checked_out_ttl_hours' => 2]);
        $resolved = $this->createGuestCart();
        $cart = $resolved->cart;
        $firstProduct = Product::factory()->create(['name' => 'Белый мрамор', 'sku' => '11000001', 'price' => '1200.00', 'stock_quantity' => 10]);
        $secondProduct = Product::factory()->create(['name' => 'Клей для плитки', 'sku' => '12000001', 'price' => '0.01', 'stock_quantity' => 4]);
        $freeProduct = Product::factory()->create(['price' => '0.00', 'stock_quantity' => 1]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $firstProduct->id, 'quantity' => 2]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $secondProduct->id, 'quantity' => 3]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $freeProduct->id, 'quantity' => 1]);

        $firstProduct->update(['price' => '1250.09']);
        $cartItems = $this->withHeader('X-Cart-Token', $resolved->token)->getJson('/api/v1/cart')
            ->assertOk()
            ->json('data.items');
        $this->assertEquals([
            $firstProduct->id => '2500.18',
            $secondProduct->id => '0.03',
            $freeProduct->id => '0.00',
        ], array_column($cartItems, 'line_total', 'product_id'));
        $response = $this->withHeader('X-Cart-Token', $resolved->token)
            ->withHeader('Idempotency-Key', 'order-create-immutable-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.payment_status', 'not_paid')
            ->assertJsonPath('data.total_amount', '2500.21')
            ->assertJsonPath('data.items.0.product_name', 'Белый мрамор')
            ->assertJsonPath('data.items.0.unit_price', '1250.09')
            ->assertJsonPath('data.items.0.line_total', '2500.18')
            ->assertJsonPath('data.items.1.product_name', 'Клей для плитки')
            ->assertJsonPath('data.items.1.line_total', '0.03')
            ->assertJsonPath('data.items.2.line_total', '0.00')
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.customer_email')
            ->assertJsonMissingPath('data.delivery_address');

        $number = $response->json('data.order_number');
        $this->assertMatchesRegularExpression('/^AC-\d{8}-[A-F0-9]{10}$/', $number);
        $this->assertDatabaseHas('orders', [
            'order_number' => $number,
            'customer_name' => 'Иван Петров',
            'customer_phone' => '+79991234567',
            'total_amount' => '2500.21',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $firstProduct->id,
            'product_name' => 'Белый мрамор',
            'product_sku' => '11000001',
            'unit_price' => '1250.09',
            'quantity' => 2,
            'line_total' => '2500.18',
        ]);
        $this->assertDatabaseCount('cart_items', 0);
        $checkedOutCart = $cart->fresh();
        $this->assertNotNull($checkedOutCart->checked_out_at);
        $this->assertTrue($checkedOutCart->expires_at->isSameSecond(
            $checkedOutCart->checked_out_at->addHours((int) config('cart.checked_out_ttl_hours')),
        ));

        $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $firstProduct->id, 'quantity' => 1])
            ->assertNotFound();

        $firstProduct->update(['name' => 'Новое название', 'price' => '1.00']);
        $order = Order::query()->where('order_number', $number)->with('items')->sole();
        $this->assertSame(PaymentStatus::NotPaid, $order->payment_status);
        $this->assertSame('Белый мрамор', $order->items->firstWhere('product_id', $firstProduct->id)->product_name);
        $this->assertSame('1250.09', $order->items->firstWhere('product_id', $firstProduct->id)->unit_price);
    }

    public function test_postgres_preserves_the_last_cent_of_the_maximum_cart_line_in_checkout(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires PostgreSQL exact numeric storage.');
        }

        $resolved = $this->createGuestCart();
        $product = Product::factory()->create([
            'price' => '9999999999.99',
            'stock_quantity' => 9999,
            'is_on_sale' => true,
        ]);
        $this->withHeader('X-Cart-Token', $resolved->token)
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 9999])
            ->assertCreated()
            ->assertJsonPath('data.line_total', '99989999999900.01');

        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items.0.line_total', '99989999999900.01');
        $this->withHeader('Idempotency-Key', 'order-create-maximum-line-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '9999999999.99')
            ->assertJsonPath('data.items.0.line_total', '99989999999900.01')
            ->assertJsonPath('data.total_amount', '99989999999900.01');
        $this->assertSame('99989999999900.01', Order::query()->sole()->total_amount);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_postgres_rejects_an_order_total_outside_numeric_range_without_consuming_the_cart(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Uses PostgreSQL to generate an oversized cart.');
        }

        $resolved = $this->createGuestCart();
        $categoryId = Product::factory()->create()->category_id;
        DB::insert(<<<'SQL'
            INSERT INTO products (category_id, name, slug, sku, unit, price, stock_quantity, is_active, is_on_sale)
            SELECT ?, 'Overflow test', 'overflow-test-' || n, 'overflow-test-' || n, 'piece',
                9999999999.99, 9999, true, true
            FROM generate_series(1, 10002) AS n
            SQL, [$categoryId]);
        DB::insert(<<<'SQL'
            INSERT INTO cart_items (cart_id, product_id, quantity)
            SELECT ?, id, 9999 FROM products WHERE slug LIKE 'overflow-test-%'
            SQL, [$resolved->cart->id]);

        $this->withHeaders([
            'X-Cart-Token' => $resolved->token,
            'Idempotency-Key' => 'order-create-overflow-0001',
        ])->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.details.cart.0', 'Сумма заказа превышает допустимый предел.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('checkout_idempotency_keys', 0);
        $this->assertSame(10002, $resolved->cart->items()->count());
        $this->assertNull($resolved->cart->fresh()->checked_out_at);
    }

    public function test_checkout_rejects_empty_or_unavailable_carts_without_creating_an_order(): void
    {
        $emptyCart = $this->createGuestCart();
        $this->withHeader('X-Cart-Token', $emptyCart->token)
            ->withHeader('Idempotency-Key', 'order-create-empty-cart-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.details.cart.0', 'Корзина пуста.');

        $resolved = $this->createGuestCart();
        $cart = $resolved->cart;
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $item = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
        $product->update(['stock_quantity' => 1]);

        $this->withHeader('X-Cart-Token', $resolved->token)
            ->withHeader('Idempotency-Key', 'order-create-unavailable-0001')
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.details.cart.0', 'Корзина содержит недоступный товар или недостаточное количество.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 2]);
    }

    public function test_checkout_rejects_missing_empty_null_whitespace_or_invalid_customer_email_without_mutating_cart(): void
    {
        $resolved = $this->createGuestCart();
        $product = Product::factory()->create();
        CartItem::query()->create(['cart_id' => $resolved->cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        foreach (['missing', null, '', '   ', 'invalid-email'] as $index => $email) {
            $payload = $this->checkoutPayload(['customer_email' => $email]);
            if ($email === 'missing') {
                unset($payload['customer_email']);
            }
            $this->withHeader('X-Cart-Token', $resolved->token)
                ->withHeader('Idempotency-Key', 'order-email-required-'.$index)
                ->postJson('/api/v1/orders', $payload)
                ->assertUnprocessable()
                ->assertJsonStructure(['error' => ['details' => ['customer_email']]]);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_mail_deliveries', 0);
        $this->assertDatabaseCount('checkout_idempotency_keys', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertNull($resolved->cart->fresh()->checked_out_at);
    }

    public function test_checkout_validates_contact_data_and_honeypot_before_using_the_cart(): void
    {
        $resolved = $this->createGuestCart();
        $cart = $resolved->cart;
        $product = Product::factory()->create();
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->withHeader('X-Cart-Token', $resolved->token)
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

    public function test_storefront_checkout_creates_and_replays_an_order_without_a_session_csrf_token(): void
    {
        $this->app['env'] = 'local'; // Exercise the CSRF middleware normally bypassed in tests.
        config(['sanctum.stateful' => ['localhost:3000']]);
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/checkout']);

        $resolved = $this->createGuestCart();
        $cart = $resolved->cart;
        $product = Product::factory()->create(['price' => '100.00', 'stock_quantity' => 2]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $key = 'order-create-replay-test-0001';

        $this->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['cart_token', 'idempotency_key']]]);

        $first = $this->withHeader('X-Cart-Token', $resolved->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $this->checkoutPayload())
            ->assertCreated();
        $second = $this->withHeader('X-Cart-Token', $resolved->token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $this->checkoutPayload(['customer_phone' => '9991234567']))
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.order_number'), $second->json('data.order_number'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_rejects_reusing_an_idempotency_key_with_different_data(): void
    {
        $resolved = $this->createGuestCart();
        $cart = $resolved->cart;
        $product = Product::factory()->create(['stock_quantity' => 2]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $key = 'order-create-conflict-test-001';

        $this->withHeader('X-Cart-Token', $resolved->token)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/orders', $this->checkoutPayload())->assertCreated();
        $this->withHeader('X-Cart-Token', $resolved->token)->withHeader('Idempotency-Key', $key)
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
