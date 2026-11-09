<?php

namespace Tests\Feature\Api;

use App\Http\Resources\AdminOrderResource;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderNotificationMail;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Queries\OrderQuery;
use App\Services\OrderConfirmationService;
use App\Services\OrderCreationService;
use App\Services\Retention\OrderDataDestructionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesGuestCarts;
use Tests\TestCase;

class ReadableOrderNumberTest extends TestCase
{
    use CreatesGuestCarts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_same_phone_reuses_customer_and_counts_orders_despite_changed_contact_details(): void
    {
        $first = $this->checkout('first-order-00000001')->assertCreated();
        $number = $first->json('data.order_number');
        $this->assertMatchesRegularExpression('/^[1-9][0-9]*-1$/D', $number);
        $customerId = (int) explode('-', $number)[0];

        $this->checkout('second-order-0000002', [
            'customer_phone' => '8 (999) 123-45-67',
            'customer_name' => 'Другой получатель',
            'customer_email' => 'other@example.test',
        ])->assertCreated()->assertJsonPath('data.order_number', $customerId.'-2');
        $this->travel(1)->years();
        $this->checkout('third-order-00000003', ['customer_phone' => '9991234567'])
            ->assertCreated()->assertJsonPath('data.order_number', $customerId.'-3');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'phone' => '+79991234567', 'last_order_sequence' => 3]);
        $this->assertDatabaseHas('orders', [
            'order_number' => $customerId.'-1', 'customer_id' => $customerId,
            'customer_order_sequence' => 1, 'customer_name' => 'Иван Петров',
            'customer_email' => 'ivan@example.test',
        ]);
        $this->assertDatabaseHas('orders', ['order_number' => $customerId.'-2', 'customer_name' => 'Другой получатель']);
    }

    public function test_different_phones_get_different_customer_ids_and_start_at_one(): void
    {
        $one = $this->checkout('different-phone-0001')->assertCreated()->json('data.order_number');
        $two = $this->checkout('different-phone-0002', ['customer_phone' => '+79991234568'])
            ->assertCreated()->json('data.order_number');

        $this->assertNotSame($one, $two);
        $this->assertMatchesRegularExpression('/^[1-9][0-9]*-1$/D', $two);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_replay_preserves_the_number_and_does_not_consume_another_sequence(): void
    {
        $first = $this->checkout('replayed-order-00001')->assertCreated();
        $this->withHeader('Idempotency-Key', 'replayed-order-00001')
            ->postJson('/api/v1/orders', $this->payload(['customer_phone' => '9991234567']))
            ->assertOk()->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.order_number', $first->json('data.order_number'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertSame(1, (int) DB::table('customers')->sole()->last_order_sequence);
    }

    #[DataProvider('customerStates')]
    public function test_late_checkout_failure_rolls_back_customer_and_counter(bool $existing): void
    {
        if ($existing) {
            $this->checkout('existing-customer-001')->assertCreated();
        }
        $confirmation = Mockery::mock(OrderConfirmationService::class);
        $confirmation->shouldReceive('queue')->andThrow(new RuntimeException('Checkout interruption'));
        $this->app->instance(OrderConfirmationService::class, $confirmation);
        $resolved = $this->createGuestCart();
        $product = Product::factory()->create(['price' => '100.00', 'stock_quantity' => 2]);
        CartItem::query()->create(['cart_id' => $resolved->cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $interruption = null;
        try {
            app(OrderCreationService::class)->create($resolved->cart, $this->payload(), 'interrupted-order-01');
        } catch (RuntimeException $exception) {
            $interruption = $exception;
        }
        $this->assertNotNull($interruption);
        $this->assertSame('Checkout interruption', $interruption->getMessage());

        $expected = $existing ? 1 : 0;
        $this->assertDatabaseCount('orders', $expected);
        $this->assertDatabaseCount('customers', $expected);
        $this->assertDatabaseCount('checkout_idempotency_keys', $expected);
        $this->assertDatabaseCount('cart_items', 1);
        if ($existing) {
            $this->assertSame(1, (int) DB::table('customers')->sole()->last_order_sequence);
        }
    }

    public static function customerStates(): array
    {
        return ['first order' => [false], 'returning customer' => [true]];
    }

    public function test_client_cannot_supply_identity_or_sequence(): void
    {
        $this->checkout('forged-customer-0001', ['customer_id' => 124, 'customer_order_sequence' => 8])
            ->assertUnprocessable()->assertJsonValidationErrors(['customer_id', 'customer_order_sequence'], 'error.details');
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_old_numbers_and_search_remain_intact_while_new_orders_start_at_one(): void
    {
        $old = Order::factory()->create(['order_number' => 'AC-20261105-35B8D7ED58', 'customer_phone' => '+79991234567']);
        $this->checkout('new-after-legacy-001')->assertCreated();
        $this->assertSame('AC-20261105-35B8D7ED58', $old->fresh()->order_number);
        $this->assertNull($old->fresh()->customer_id);
        $this->assertSame(1, (int) DB::table('customers')->sole()->last_order_sequence);
        $this->assertSame($old->id, app(OrderQuery::class)->paginate(['search' => $old->order_number], 15)->sole()->id);
        $new = Order::query()->whereKeyNot($old->id)->sole();
        $this->assertSame($new->id, app(OrderQuery::class)->paginate(['search' => $new->order_number], 15)->sole()->id);
    }

    public function test_both_email_messages_use_the_number_returned_by_checkout(): void
    {
        $number = $this->checkout('emailed-order-000001')->assertCreated()->json('data.order_number');
        $order = Order::query()->with('items')->sole();
        foreach ([new OrderConfirmationMail($order), new OrderNotificationMail($order)] as $mail) {
            $this->assertStringContainsString($number, $mail->envelope()->subject);
            $this->assertStringContainsString($number, $mail->render());
        }
    }

    public function test_anonymized_admin_snapshot_matches_the_documented_number_and_contact_types(): void
    {
        $this->checkout('anonymized-order-001')->assertCreated();
        $order = Order::query()->sole();
        app(OrderDataDestructionService::class)->anonymize($order, CarbonImmutable::now());
        $snapshot = (new AdminOrderResource($order->fresh()->load(['items', 'assignee'])))->resolve(request());
        $spec = json_decode(file_get_contents(base_path('../docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $properties = $spec['components']['schemas']['AdminOrder']['properties'];

        $this->assertMatchesRegularExpression('~'.$properties['order_number']['pattern'].'~D', $snapshot['order_number']);
        $this->assertNull($snapshot['customer']['name']);
        $this->assertNull($snapshot['delivery_address']);
        $this->assertContains(get_debug_type($snapshot['customer']['name']), (array) $properties['customer']['properties']['name']['type']);
        $this->assertContains(get_debug_type($snapshot['delivery_address']), (array) $properties['delivery_address']['type']);
    }

    private function checkout(string $key, array $overrides = []): TestResponse
    {
        $resolved = $this->createGuestCart();
        $product = Product::factory()->create(['price' => '100.00', 'stock_quantity' => 2]);
        CartItem::query()->create(['cart_id' => $resolved->cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        return $this->withHeaders(['X-Cart-Token' => $resolved->token, 'Idempotency-Key' => $key])
            ->postJson('/api/v1/orders', $this->payload($overrides));
    }

    private function payload(array $overrides = []): array
    {
        return [
            'customer_name' => 'Иван Петров', 'customer_phone' => '+7 (999) 123-45-67',
            'customer_email' => 'ivan@example.test', 'delivery_address' => 'Москва, улица Пример, 1',
            ...$overrides,
        ];
    }
}
