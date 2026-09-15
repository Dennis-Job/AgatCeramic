<?php

namespace Tests\Integration;

use App\Models\Product;
use App\Services\CartItemManagementService;
use App\Services\GuestCartService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class PostgresGuestCartLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('CI') !== 'true') {
            $this->markTestSkipped('This integration test runs only in CI.');
        }

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This integration test requires PostgreSQL.');
        }
    }

    public function test_postgresql_has_token_and_expiry_indexes(): void
    {
        $indexes = collect(DB::select("select indexdef from pg_indexes where schemaname = current_schema() and tablename = 'carts'"))
            ->pluck('indexdef')
            ->implode("\n");

        $this->assertStringContainsString('UNIQUE', $indexes);
        $this->assertStringContainsString('(token_hash)', $indexes);
        $this->assertStringContainsString('(expires_at, id)', $indexes);
    }

    public function test_cleanup_skips_a_cart_locked_by_an_active_write(): void
    {
        $resolved = app(GuestCartService::class)->resolve(null);
        $cart = $resolved->cart;
        $cart->update(['expires_at' => now()->addSecond()]);
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $process = null;

        DB::beginTransaction();
        try {
            app(CartItemManagementService::class)->add($cart, $product->id, 1);
            usleep(1_100_000);
            $process = $this->startCleanupWorker();
            $outcome = $this->finishWorker($process);
            $this->assertSame('ok', $outcome['status'], $outcome['message'] ?? 'Cleanup failed.');
            $this->assertSame(0, $outcome['deleted'] ?? null);
            DB::commit();
        } catch (Throwable $exception) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process !== null) {
                $process['process']->stop(1);
            }

            throw $exception;
        }

        $this->assertDatabaseHas('carts', ['id' => $cart->id]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id]);
        $this->assertTrue($cart->fresh()->expires_at->isFuture());

        $cart->delete();
        $product->delete();
    }

    /** @return array{process: Process, application_name: string} */
    private function startCleanupWorker(): array
    {
        $applicationName = 'guest-cart-cleanup-'.bin2hex(random_bytes(4));
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/guest_cart_cleanup_worker.php'),
            $applicationName,
        ]);
        $process->setTimeout(15);
        $process->start();

        $deadline = microtime(true) + 5;
        while (! str_contains($process->getOutput(), "ready\n")) {
            if (! $process->isRunning()) {
                throw new RuntimeException('Guest cart cleanup worker stopped before initialization: '.$process->getErrorOutput());
            }
            if (microtime(true) >= $deadline) {
                $process->stop();
                throw new RuntimeException('Guest cart cleanup worker did not initialize its PostgreSQL connection.');
            }
            usleep(20_000);
        }

        return ['process' => $process, 'application_name' => $applicationName];
    }

    /** @param array{process: Process, application_name: string} $worker @return array{status: string, deleted?: int, message?: string} */
    private function finishWorker(array $worker): array
    {
        $worker['process']->wait();
        $this->assertTrue($worker['process']->isSuccessful(), $worker['process']->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($worker['process']->getOutput()))));
        $encoded = end($lines);
        if (! is_string($encoded) || $encoded === 'ready') {
            throw new RuntimeException('Guest cart cleanup worker did not return an outcome.');
        }

        /** @var array{status: string, deleted?: int, message?: string} $outcome */
        $outcome = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);

        return $outcome;
    }
}
