<?php

namespace Tests\Integration;

use App\Models\Order;
use App\Models\RetentionExecution;
use App\Models\RetentionLegalHold;
use App\Models\RetentionTombstone;
use App\Services\Retention\OrderRetentionService;
use App\Services\Retention\RetentionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresRetentionControlsTest extends TestCase
{
    /** @var list<int> */
    private array $orderIds = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('CI') !== 'true') {
            $this->markTestSkipped('This integration test runs only in CI.');
        }

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This integration test requires PostgreSQL.');
        }

        CarbonImmutable::setTestNow('2026-09-14 12:00:00');
        $this->configurePolicy(1);
    }

    #[\Override]
    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($this->orderIds !== []) {
            Order::query()->whereIn('id', $this->orderIds)->delete();
        }
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_postgresql_rejects_retention_evidence_updates_and_deletes(): void
    {
        $execution = $this->execution();
        $tombstone = RetentionTombstone::query()->create([
            'id' => (string) Str::uuid(),
            'batch_id' => $execution->batch_id,
            'scope' => 'orders',
            'record_id' => 999999,
            'action' => 'order-anonymized',
            'key_id' => 'test-v1',
            'subject_hmac' => str_repeat('0', 64),
            'occurred_at' => now(),
        ]);

        foreach ([
            [RetentionExecution::query()->whereKey($execution->batch_id), ['status' => 'failed'], 'execution'],
            [RetentionTombstone::query()->whereKey($tombstone->id), ['action' => 'order-deleted'], 'tombstone'],
        ] as [$query, $changes, $label]) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    $operation === 'update' ? $query->update($changes) : $query->delete();
                    $this->fail("Retention {$label} {$operation} unexpectedly succeeded.");
                } catch (QueryException $exception) {
                    $this->assertStringContainsString('retention evidence is immutable', $exception->getMessage());
                }
            }
        }
    }

    public function test_failed_order_anonymization_rolls_back_tombstone_and_all_mutations(): void
    {
        $order = $this->expiredOrder();
        DB::statement('ALTER TABLE orders ADD CONSTRAINT retention_test_no_anonymize CHECK (anonymized_at IS NULL)');

        try {
            app(OrderRetentionService::class)->run(false);
            $this->fail('Retention apply unexpectedly bypassed the PostgreSQL constraint.');
        } catch (QueryException) {
            $this->assertSame('Synthetic Person', $order->fresh()?->customer_name);
            $this->assertDatabaseMissing('retention_tombstones', [
                'scope' => 'orders',
                'record_id' => $order->id,
            ]);
        } finally {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS retention_test_no_anonymize');
        }
    }

    public function test_postgresql_rejects_overbroad_or_duplicate_active_legal_holds(): void
    {
        $order = $this->expiredOrder();
        RetentionLegalHold::query()->create([
            'scope' => 'orders',
            'record_id' => $order->id,
            'case_reference' => 'CASE-VALID',
            'legal_basis' => 'Synthetic legal hold',
            'owner_reference' => 'privacy-team',
            'started_at' => now(),
            'review_at' => now()->addDays(90),
        ]);

        foreach ([
            ['case_reference' => 'CASE-DUPLICATE', 'review_at' => now()->addDays(30)],
            ['case_reference' => 'CASE-TOO-LONG', 'record_id' => $order->id + 1000, 'review_at' => now()->addDays(91)],
        ] as $attributes) {
            try {
                RetentionLegalHold::query()->create(array_merge([
                    'scope' => 'orders',
                    'record_id' => $order->id,
                    'legal_basis' => 'Synthetic legal hold',
                    'owner_reference' => 'privacy-team',
                    'started_at' => now(),
                ], $attributes));
                $this->fail('Invalid legal hold unexpectedly succeeded.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_postgresql_skip_locked_keeps_concurrent_batches_bounded(): void
    {
        $locked = $this->expiredOrder();
        $available = $this->expiredOrder();
        DB::beginTransaction();
        Order::query()->whereKey($locked->id)->lockForUpdate()->sole();

        $process = new Process([PHP_BINARY, base_path('tests/Support/retention_concurrency_worker.php')]);
        $process->setTimeout(15);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        $encodedOutcome = end($lines);
        if (! is_string($encodedOutcome)) {
            throw new RuntimeException('Retention worker returned no result.');
        }
        $outcome = json_decode($encodedOutcome, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($outcome)) {
            throw new RuntimeException('Retention worker returned an invalid result.');
        }
        $this->assertSame(1, $outcome['processed'] ?? null);
        $this->assertSame('Synthetic Person', $locked->fresh()?->customer_name);
        $this->assertNull($available->fresh()?->customer_name);
        DB::rollBack();
    }

    private function expiredOrder(): Order
    {
        $order = Order::factory()->create([
            'status' => 'completed',
            'completed_at' => CarbonImmutable::now()->subYears(4),
            'customer_name' => 'Synthetic Person',
            'customer_phone' => '+79990001122',
            'customer_email' => 'synthetic@example.test',
            'delivery_address' => 'Synthetic address',
        ]);
        $this->orderIds[] = (int) $order->id;

        return $order;
    }

    private function configurePolicy(int $batchSize): void
    {
        config()->set('retention.policy_status', 'accepted');
        config()->set('retention.apply_enabled', true);
        config()->set('retention.tombstone_key', 'synthetic-postgres-test-key');
        config()->set('retention.orders.commercial_disposition', 'retain_commercial');
        config()->set('retention.batch_size', $batchSize);
        app()->forgetInstance(RetentionPolicy::class);
    }

    private function execution(): RetentionExecution
    {
        return RetentionExecution::query()->create([
            'batch_id' => (string) Str::uuid(),
            'policy_version' => 'test',
            'scope' => 'orders',
            'action' => 'test',
            'mode' => 'dry-run',
            'status' => 'completed',
            'service_identity' => 'test',
            'occurred_at' => now(),
        ]);
    }
}
