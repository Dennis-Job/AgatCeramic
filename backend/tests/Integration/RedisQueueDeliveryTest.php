<?php

namespace Tests\Integration;

use App\Jobs\DeleteStoredFile;
use App\Jobs\ProcessProductImport;
use App\Jobs\SendOrderConfirmation;
use App\Models\Category;
use App\Models\ImportDispatchTask;
use App\Models\Order;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\StorageCleanupTask;
use App\Models\User;
use App\Services\ImportDispatchService;
use App\Services\OrderConfirmationService;
use App\Support\ProductWorkbookSchema;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RedisQueueDeliveryTest extends TestCase
{
    private const QUEUE = 'default';

    /** @var list<string> */
    private array $createdFiles = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('redis', config('queue.default'));
        $this->assertSame('agatceramic_queue_test', config('database.connections.pgsql.database'));
        $this->assertSame('14', (string) config('database.redis.queue.database'));
        $this->assertSame('agatceramic-queue-test:', config('database.redis.options.prefix'));

        Redis::connection('queue')->flushdb();
    }

    #[\Override]
    protected function tearDown(): void
    {
        Redis::connection('queue')->flushdb();

        foreach ($this->createdFiles as $path) {
            Storage::disk('local')->delete($path);
        }

        parent::tearDown();
    }

    public function test_representative_jobs_are_delivered_by_real_redis_workers(): void
    {
        $this->runImportAndCleanupFlow();
        $this->runOrderConfirmationFlow();
        $this->runStaleCleanupRecoveryFlow();
        $this->runTerminalFailureFlow();
    }

    private function runImportAndCleanupFlow(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());
        $category = Category::factory()->create(['name' => 'Queue Tile', 'slug' => 'queue-tile']);
        $path = 'product-imports/queue-delivery-smoke.xlsx';
        $this->createdFiles[] = $path;
        $source = $this->workbook($category);
        Storage::disk('local')->put($path, file_get_contents($source));
        unlink($source);
        $import = ProductImport::query()->create([
            'user_id' => $actor->id,
            'original_filename' => 'payload-sentinel-import.xlsx',
            'disk' => 'local',
            'path' => $path,
            'status' => 'pending',
        ]);

        DB::transaction(function () use ($import): void {
            app(ImportDispatchService::class)->scheduleProductImport($import);
            $this->assertSame(0, $this->queueSize(), 'Import must not be visible before commit.');
        });

        $this->assertSame(1, $this->queueSize());
        $this->assertIdentifierOnlyPayload(
            $this->soleReadyPayload(),
            ProcessProductImport::class,
            'productImportId',
            $import->id,
            ['payload-sentinel-import.xlsx', $path, $actor->email],
        );

        $worker = $this->runWorker(stopWhenEmpty: true, maxTime: 20);

        $this->assertStringContainsString(ProcessProductImport::class, $worker);
        $this->assertStringContainsString(DeleteStoredFile::class, $worker);
        $this->assertSame(0, $this->queueSize());
        $this->assertSame('completed', $import->refresh()->status);
        $this->assertDatabaseHas('products', ['slug' => 'queue-worker-product']);
        $this->assertDatabaseHas('import_dispatch_tasks', [
            'import_type' => ImportDispatchTask::TYPE_PRODUCT,
            'import_id' => $import->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('storage_cleanup_tasks', [
            'disk' => 'local',
            'path' => $path,
            'status' => 'completed',
            'attempts' => 1,
        ]);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    private function runOrderConfirmationFlow(): void
    {
        $order = Order::factory()->create([
            'customer_name' => 'Queue Recipient',
            'customer_phone' => '+70000000000',
            'customer_email' => 'payload-sentinel-recipient@example.test',
            'delivery_address' => 'Synthetic queue test address',
        ]);
        $order->items()->create([
            'product_name' => 'Queue product snapshot',
            'product_sku' => '99000001',
            'unit_price' => '1000.00',
            'quantity' => 1,
            'line_total' => '1000.00',
        ]);

        DB::transaction(function () use ($order): void {
            app(OrderConfirmationService::class)->queue($order);
            $this->assertSame(0, $this->queueSize(), 'Confirmation must not be visible before commit.');
        });

        $this->assertSame(1, $this->queueSize());
        $this->assertIdentifierOnlyPayload(
            $this->soleReadyPayload(),
            SendOrderConfirmation::class,
            'orderId',
            $order->id,
            [$order->customer_name, $order->customer_phone, $order->customer_email, $order->delivery_address],
        );

        $worker = $this->runWorker(stopWhenEmpty: true, maxTime: 15);

        $this->assertStringContainsString(SendOrderConfirmation::class, $worker);
        $this->assertSame(0, $this->queueSize());
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    private function runStaleCleanupRecoveryFlow(): void
    {
        $path = 'queue-smoke/stale-cleanup.txt';
        $this->createdFiles[] = $path;
        Storage::disk('local')->put($path, 'queue-smoke');
        $task = StorageCleanupTask::query()->create([
            'disk' => 'local',
            'path' => $path,
            'status' => 'processing',
            'last_attempted_at' => now()->subMinutes(11),
            'dispatched_at' => now()->subMinutes(11),
        ]);

        $this->assertSame(0, Artisan::call('storage-cleanup:retry', ['--limit' => 10]));
        $this->assertSame(1, $this->queueSize());
        $this->assertSame(0, Artisan::call('storage-cleanup:retry', ['--limit' => 10]));
        $this->assertSame(1, $this->queueSize(), 'A fresh redispatch must not be duplicated.');

        DeleteStoredFile::dispatch($task->id);
        $this->assertSame(2, $this->queueSize());
        foreach ($this->readyPayloads() as $payload) {
            $this->assertIdentifierOnlyPayload($payload, DeleteStoredFile::class, 'cleanupTaskId', $task->id, [$path]);
        }

        $worker = $this->runWorker(stopWhenEmpty: true, maxTime: 15);

        $this->assertStringContainsString(DeleteStoredFile::class, $worker);
        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(1, $task->attempts, 'Duplicate delivery must stay idempotent.');
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, $this->queueSize());
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    private function runTerminalFailureFlow(): void
    {
        $task = StorageCleanupTask::query()->create([
            'disk' => 'queue-smoke-missing-disk',
            'path' => 'payload-sentinel-failure-path.txt',
        ]);
        DeleteStoredFile::dispatch($task->id);
        $payload = $this->soleReadyPayload();
        $this->assertSame('1,1', $payload['backoff'] ?? null);
        $this->assertSame(3, $payload['maxTries'] ?? null);
        $this->assertIdentifierOnlyPayload(
            $payload,
            DeleteStoredFile::class,
            'cleanupTaskId',
            $task->id,
            [$task->path],
        );

        $startedAt = microtime(true);
        $worker = $this->runWorker(stopWhenEmpty: false, maxTime: 8);
        $elapsed = microtime(true) - $startedAt;

        $this->assertStringContainsString(DeleteStoredFile::class, $worker);
        $this->assertGreaterThanOrEqual(2.0, $elapsed, 'The real worker must respect both retry delays.');
        $this->assertLessThan(15.0, $elapsed, 'The integration worker exceeded its bounded timeout.');
        $this->assertSame(0, $this->queueSize());
        $task->refresh();
        $this->assertSame('failed', $task->status);
        $this->assertSame(3, $task->attempts);
        $this->assertNotNull($task->next_attempt_at);
        $this->assertDatabaseCount('failed_jobs', 1);

        $failedPayload = json_decode((string) DB::table('failed_jobs')->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIdentifierOnlyPayload(
            $failedPayload,
            DeleteStoredFile::class,
            'cleanupTaskId',
            $task->id,
            [$task->path],
        );
    }

    /** @return array<string, mixed> */
    private function soleReadyPayload(): array
    {
        $payloads = $this->readyPayloads();
        $this->assertCount(1, $payloads);

        return $payloads[0];
    }

    /** @return list<array<string, mixed>> */
    private function readyPayloads(): array
    {
        $payloads = Redis::connection('queue')->lrange('queues:'.self::QUEUE, 0, -1);

        return array_values(array_map(
            static fn (string $payload): array => json_decode($payload, true, 512, JSON_THROW_ON_ERROR),
            $payloads,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  class-string  $expectedClass
     * @param  list<string|null>  $forbiddenValues
     */
    private function assertIdentifierOnlyPayload(
        array $payload,
        string $expectedClass,
        string $identifierProperty,
        int $identifier,
        array $forbiddenValues,
    ): void {
        $this->assertSame($expectedClass, $payload['displayName'] ?? null);
        $command = $payload['data']['command'] ?? null;
        $this->assertIsString($command);
        $job = unserialize($command, ['allowed_classes' => [$expectedClass]]);
        $this->assertInstanceOf($expectedClass, $job);
        $this->assertSame($identifier, $job->{$identifierProperty});

        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        foreach ($forbiddenValues as $value) {
            if (is_string($value) && $value !== '') {
                $this->assertStringNotContainsString($value, $rawPayload);
            }
        }
    }

    private function queueSize(): int
    {
        return Queue::connection('redis')->size(self::QUEUE);
    }

    private function runWorker(bool $stopWhenEmpty, int $maxTime): string
    {
        $command = [
            PHP_BINARY,
            'artisan',
            'queue:work',
            'redis',
            '--queue='.self::QUEUE,
            '--sleep=1',
            '--max-time='.$maxTime,
            '--memory=128',
            '--verbose',
        ];
        if ($stopWhenEmpty) {
            $command[] = '--stop-when-empty';
        }

        $process = new Process($command, base_path(), null, null, $maxTime + 7);
        $process->run();
        $this->assertTrue($process->isSuccessful(), 'Queue worker exited unsuccessfully.');

        return $process->getOutput().$process->getErrorOutput();
    }

    private function workbook(Category $category): string
    {
        $path = tempnam(storage_path('framework/testing'), 'queue-import-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(ProductWorkbookSchema::BASE_HEADERS));
        $values = [
            'name' => 'Queue Worker Product',
            'slug' => 'queue-worker-product',
            'category_slug' => $category->slug,
            'unit' => 'piece',
            'price' => 1000,
            'stock_quantity' => 1,
            'is_active' => false,
            'is_on_sale' => false,
        ];
        $writer->addRow(Row::fromValues(array_map(
            static fn (string $header): mixed => $values[$header] ?? null,
            ProductWorkbookSchema::BASE_HEADERS,
        )));
        $writer->close();

        return $path;
    }
}
