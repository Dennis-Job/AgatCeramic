<?php

namespace Tests\Integration;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderCreationService;
use App\Services\Retention\OrderDataDestructionService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class PostgresReadableOrderNumberTest extends TestCase
{
    /** @var list<int> */
    private array $cartIds = [];

    /** @var list<int> */
    private array $productIds = [];

    /** @var list<int> */
    private array $customerIds = [];

    private string $phone;

    private string $email;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('CI') !== 'true') {
            $this->markTestSkipped('This integration test runs only in CI.');
        }
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This integration test requires PostgreSQL.');
        }

        $this->phone = '+79'.random_int(100000000, 999999999);
        $this->email = 'readable-order-'.bin2hex(random_bytes(8)).'@example.test';
        Queue::fake();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        if (isset($this->email)) {
            $orders = Order::query()->where('customer_email', $this->email)->get();
            $this->customerIds = array_merge($this->customerIds, $orders->pluck('customer_id')->filter()->all());
            Order::query()->whereIn('id', $orders->modelKeys())->delete();
            // Anonymized orders no longer carry the synthetic email.
            $orderIds = DB::table('order_items')->whereIn('product_id', $this->productIds)->pluck('order_id');
            Order::query()->whereIn('id', $orderIds)->delete();
            DB::table('customers')->whereIn('id', $this->customerIds)->orWhere('phone', $this->phone)->delete();
            Cart::query()->whereIn('id', $this->cartIds)->delete();
            Product::query()->whereIn('id', $this->productIds)->delete();
        }

        parent::tearDown();
    }

    public function test_parallel_first_checkouts_share_one_customer_and_allocate_distinct_numbers(): void
    {
        $firstCart = $this->cart();
        $secondCart = $this->cart();
        $first = null;

        $outcome = $this->runCompetingTransactions(
            function () use ($firstCart, &$first): void {
                $first = $this->checkout($firstCart);
            },
            'checkout',
            $this->checkoutPayload($secondCart),
        );

        $this->assertInstanceOf(Order::class, $first);
        $second = $this->orderFromOutcome($outcome);
        $this->assertNumber($first, (int) $first->customer_id, 1);
        $this->assertNumber($second, (int) $first->customer_id, 2);
        $this->assertSame(1, DB::table('customers')->where('phone', $this->phone)->count());
        $this->assertSame(2, (int) DB::table('customers')->where('id', $first->customer_id)->value('last_order_sequence'));
        $this->assertCompletedCheckout($firstCart, $first);
        $this->assertCompletedCheckout($secondCart, $second);
    }

    public function test_parallel_subsequent_checkouts_continue_the_existing_customer_sequence(): void
    {
        $initial = $this->checkout($this->cart());
        $firstCart = $this->cart();
        $secondCart = $this->cart();
        $first = null;

        $outcome = $this->runCompetingTransactions(
            function () use ($firstCart, &$first): void {
                $first = $this->checkout($firstCart);
            },
            'checkout',
            $this->checkoutPayload($secondCart),
        );

        $this->assertInstanceOf(Order::class, $first);
        $second = $this->orderFromOutcome($outcome);
        $customerId = (int) $initial->customer_id;
        $this->assertNumber($initial, $customerId, 1);
        $this->assertNumber($first, $customerId, 2);
        $this->assertNumber($second, $customerId, 3);
        $this->assertSame(1, DB::table('customers')->where('phone', $this->phone)->count());
        $this->assertSame(3, (int) DB::table('customers')->where('id', $customerId)->value('last_order_sequence'));
        $this->assertCompletedCheckout($firstCart, $first);
        $this->assertCompletedCheckout($secondCart, $second);
    }

    #[DataProvider('destructionOperations')]
    public function test_cleanup_waiting_for_checkout_preserves_the_phone_of_the_new_order(string $operation): void
    {
        $initial = $this->checkout($this->cart());
        $customerId = (int) $initial->customer_id;
        $cart = $this->cart();
        $created = null;

        $outcome = $this->runCompetingTransactions(
            function () use ($cart, &$created): void {
                $created = $this->checkout($cart);
            },
            $operation,
            ['order_id' => $initial->id],
        );

        $this->assertOutcomeOk($outcome);
        $this->assertInstanceOf(Order::class, $created);
        $this->assertNumber($created, $customerId, 2);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'phone' => $this->phone, 'last_order_sequence' => 2]);
        $this->assertDestroyedOrder($initial, $operation);
        $this->assertCompletedCheckout($cart, $created);

        $next = $this->checkout($this->cart());
        $this->assertNumber($next, $customerId, 3);
    }

    #[DataProvider('destructionOperations')]
    public function test_checkout_waiting_for_last_order_cleanup_gets_a_new_customer_id(string $operation): void
    {
        $initial = $this->checkout($this->cart());
        $oldCustomerId = (int) $initial->customer_id;
        $cart = $this->cart();

        $outcome = $this->runCompetingTransactions(
            function () use ($initial, $operation): void {
                $this->destroy($initial, $operation);
            },
            'checkout',
            $this->checkoutPayload($cart),
        );

        $created = $this->orderFromOutcome($outcome);
        $this->assertNotSame($oldCustomerId, (int) $created->customer_id);
        $this->assertNumber($created, (int) $created->customer_id, 1);
        $this->assertDatabaseHas('customers', ['id' => $oldCustomerId, 'phone' => null, 'last_order_sequence' => 1]);
        $this->assertDatabaseHas('customers', ['id' => $created->customer_id, 'phone' => $this->phone, 'last_order_sequence' => 1]);
        $this->assertSame(1, DB::table('customers')->where('phone', $this->phone)->count());
        $this->assertDestroyedOrder($initial, $operation);
        $this->assertCompletedCheckout($cart, $created);
    }

    public static function destructionOperations(): array
    {
        return [['anonymize'], ['delete']];
    }

    private function cart(): Cart
    {
        $cart = Cart::factory()->create();
        // Distinct products ensure PostgreSQL contention comes from customer allocation.
        $product = Product::factory()->create(['price' => '123.45', 'stock_quantity' => 10, 'is_active' => true]);
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->cartIds[] = $cart->id;
        $this->productIds[] = $product->id;

        return $cart;
    }

    private function checkout(Cart $cart): Order
    {
        $payload = $this->checkoutPayload($cart);
        $order = app(OrderCreationService::class)->create($cart, $payload['attributes'], $payload['idempotency_key'])->order;
        $this->customerIds[] = (int) $order->customer_id;

        return $order;
    }

    /** @return array{cart_id: int, attributes: array<string, string>, idempotency_key: string} */
    private function checkoutPayload(Cart $cart): array
    {
        return [
            'cart_id' => $cart->id,
            'attributes' => [
                'customer_name' => 'Synthetic checkout customer',
                'customer_phone' => $this->phone,
                'customer_email' => $this->email,
                'delivery_address' => 'Synthetic test address',
            ],
            'idempotency_key' => 'readable-number-'.bin2hex(random_bytes(16)),
        ];
    }

    private function destroy(Order $order, string $operation): void
    {
        $service = app(OrderDataDestructionService::class);
        if ($operation === 'anonymize') {
            $service->anonymize($order, CarbonImmutable::now());
        } else {
            $service->delete($order);
        }
    }

    private function assertDestroyedOrder(Order $order, string $operation): void
    {
        if ($operation === 'delete') {
            $this->assertDatabaseMissing('orders', ['id' => $order->id]);

            return;
        }

        $order->refresh();
        $this->assertNull($order->customer_id);
        $this->assertNull($order->customer_order_sequence);
        $this->assertNull($order->customer_phone);
        $this->assertNotNull($order->anonymized_at);
        $this->assertStringStartsWith('ANON-', $order->order_number);
    }

    private function assertNumber(Order $order, int $customerId, int $sequence): void
    {
        $this->assertSame($customerId, (int) $order->customer_id);
        $this->assertSame($sequence, (int) $order->customer_order_sequence);
        $this->assertSame($customerId.'-'.$sequence, $order->order_number);
    }

    private function assertCompletedCheckout(Cart $cart, Order $order): void
    {
        $this->assertNotNull($cart->fresh()->checked_out_at);
        $this->assertSame(0, $cart->items()->count());
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('123.45', $order->total_amount);
    }

    /**
     * @param  Closure(): void  $firstOperation
     * @param  array<string, mixed>  $payload
     * @return array{status: string, order_id?: int, class?: string, message?: string}
     */
    private function runCompetingTransactions(Closure $firstOperation, string $operation, array $payload): array
    {
        DB::beginTransaction();
        $worker = null;

        try {
            $firstOperation();
            $worker = $this->startWorker($operation, $payload);
            $this->assertWorkerWaitsForLock($worker['application_name']);
            DB::commit();

            return $this->finishWorker($worker);
        } catch (Throwable $exception) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($worker !== null) {
                $worker['process']->stop(1);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload @return array{process: Process, application_name: string} */
    private function startWorker(string $operation, array $payload): array
    {
        $applicationName = 'readable-order-'.bin2hex(random_bytes(8));
        $payload['application_name'] = $applicationName;
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/readable_order_number_worker.php'),
            $operation,
            base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
        $process->setTimeout(15);
        $process->start();

        $deadline = microtime(true) + 5;
        while (! str_contains($process->getOutput(), "ready\n")) {
            if (! $process->isRunning()) {
                throw new RuntimeException('Readable order worker stopped before initialization: '.$process->getErrorOutput());
            }
            if (microtime(true) >= $deadline) {
                $process->stop(1);
                throw new RuntimeException('Readable order worker did not initialize its PostgreSQL connection.');
            }
            usleep(20_000);
        }

        return ['process' => $process, 'application_name' => $applicationName];
    }

    private function assertWorkerWaitsForLock(string $applicationName): void
    {
        $deadline = microtime(true) + 5;
        do {
            $activity = DB::selectOne('select wait_event_type from pg_stat_activity where application_name = ?', [$applicationName]);
            if ($activity?->wait_event_type === 'Lock') {
                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        $this->fail('The competing checkout/cleanup did not wait for a PostgreSQL lock.');
    }

    /**
     * @param  array{process: Process, application_name: string}  $worker
     * @return array{status: string, order_id?: int, class?: string, message?: string}
     */
    private function finishWorker(array $worker): array
    {
        $worker['process']->wait();
        $this->assertTrue($worker['process']->isSuccessful(), $worker['process']->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($worker['process']->getOutput()))));
        $encoded = end($lines);
        if (! is_string($encoded) || $encoded === 'ready') {
            throw new RuntimeException('Readable order worker did not return an outcome.');
        }

        return json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array{status: string, order_id?: int, class?: string, message?: string} $outcome */
    private function orderFromOutcome(array $outcome): Order
    {
        $this->assertOutcomeOk($outcome);
        $this->assertArrayHasKey('order_id', $outcome);
        $order = Order::query()->findOrFail($outcome['order_id']);
        $this->customerIds[] = (int) $order->customer_id;

        return $order;
    }

    /** @param array{status: string, class?: string, message?: string} $outcome */
    private function assertOutcomeOk(array $outcome): void
    {
        $this->assertSame('ok', $outcome['status'], ($outcome['class'] ?? '').': '.($outcome['message'] ?? 'Worker failed.'));
    }
}
