<?php

namespace Tests\Feature\Retention;

use App\Models\Order;
use App\Services\OrderNumberService;
use App\Services\Retention\OrderDataDestructionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OrderCustomerRetentionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('destructionActions')]
    public function test_destruction_keeps_phone_while_another_order_remains_and_erases_it_after_last(string $action): void
    {
        $first = $this->order();
        $second = $this->order();
        $customerId = $first->customer_id;
        $this->destroy($first, $action);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'phone' => '+79991234567', 'last_order_sequence' => 2]);
        if ($action === 'anonymize') {
            $this->assertNull($first->fresh()->customer_id);
            $this->assertNull($first->fresh()->customer_order_sequence);
            $this->assertNull($first->fresh()->customer_phone);
            $this->assertStringStartsWith('ANON-', $first->fresh()->order_number);
        }

        $this->destroy($second, $action);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'phone' => null, 'last_order_sequence' => 2]);
        $new = $this->order();
        $this->assertNotSame($customerId, $new->customer_id);
        $this->assertSame(1, $new->customer_order_sequence);
        $this->assertSame($new->customer_id.'-1', $new->order_number);
    }

    #[DataProvider('destructionActions')]
    public function test_destruction_rolls_back_the_order_and_phone_together(string $action): void
    {
        $order = $this->order();
        $customerId = $order->customer_id;
        try {
            DB::transaction(function () use ($order, $action): void {
                $this->destroy($order, $action);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $customerId, 'customer_order_sequence' => 1, 'anonymized_at' => null]);
        $this->assertDatabaseHas('customers', ['phone' => '+79991234567']);
    }

    public function test_legacy_orders_can_still_be_anonymized_without_a_customer(): void
    {
        $order = Order::factory()->create();
        $this->destroy($order, 'anonymize');
        $this->assertNull($order->fresh()->customer_id);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_migration_refuses_to_discard_reserved_ids_even_after_all_pii_is_erased(): void
    {
        $order = $this->order();
        $id = $order->customer_id;
        $this->destroy($order, 'delete');
        $migration = require database_path('migrations/2026_11_09_000001_add_customer_order_numbers.php');
        $failure = null;
        try {
            $migration->down();
        } catch (RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertNotNull($failure);
        $this->assertDatabaseHas('customers', ['id' => $id, 'last_order_sequence' => 1]);
    }

    public function test_migration_can_roll_back_before_number_allocation_without_rewriting_legacy_orders(): void
    {
        $order = Order::factory()->create(['order_number' => 'AC-20261105-35B8D7ED58']);
        $migration = require database_path('migrations/2026_11_09_000001_add_customer_order_numbers.php');
        $migration->down();
        $migration->up();
        $this->assertSame('AC-20261105-35B8D7ED58', $order->fresh()->order_number);
        $this->assertDatabaseCount('customers', 0);
        $this->assertNull($order->fresh()->customer_id);
    }

    public static function destructionActions(): array
    {
        return ['anonymize' => ['anonymize'], 'delete' => ['delete']];
    }

    private function order(): Order
    {
        return DB::transaction(fn (): Order => Order::factory()->create([
            ...app(OrderNumberService::class)->generate('+79991234567'),
            'customer_phone' => '+79991234567',
        ]));
    }

    private function destroy(Order $order, string $action): void
    {
        $service = app(OrderDataDestructionService::class);
        if ($action === 'delete') {
            $service->delete($order);
        } else {
            $service->anonymize($order, CarbonImmutable::now()->subYears(4));
        }
    }
}
