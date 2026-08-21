<?php

namespace Tests\Integration;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRelation;
use App\Models\User;
use App\Services\CategoryManagementService;
use App\Services\ProductImageManagementService;
use App\Services\ProductManagementService;
use App\Services\ProductRelationManagementService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class PostgresCatalogConcurrencyTest extends TestCase
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

    public function test_competing_primary_image_uploads_preserve_one_primary_image(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create();
        Storage::disk('public')->deleteDirectory("product-images/{$product->id}");
        $firstUpload = UploadedFile::fake()->createWithContent('first.jpg', 'first concurrent image');

        $outcome = $this->runCompetingTransactions(
            'primary-image',
            fn () => app(ProductImageManagementService::class)->create($actor, $product, $firstUpload, ['is_primary' => true]),
            'upload-image',
            ['actor_id' => $actor->id, 'product_id' => $product->id, 'is_primary' => true],
        );

        $this->assertOutcomeOk($outcome);
        $this->assertSame(1, ProductImage::query()
            ->whereBelongsTo($product)
            ->where('is_primary', true)
            ->count());
        $this->assertSame(2, ProductImage::query()->whereBelongsTo($product)->count());
        app(ProductManagementService::class)->delete($actor, $product);
        $this->assertSame([], Storage::disk('public')->allFiles("product-images/{$product->id}"));
    }

    public function test_product_deletion_wins_against_an_upload_waiting_on_the_product_lock(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create();
        Storage::disk('public')->deleteDirectory("product-images/{$product->id}");

        $outcome = $this->runCompetingTransactions(
            'delete-upload',
            fn () => Product::query()->whereKey($product->id)->lockForUpdate()->sole(),
            'upload-image',
            ['actor_id' => $actor->id, 'product_id' => $product->id],
            fn () => app(ProductManagementService::class)->delete($actor, $product),
        );

        $this->assertThrowableIs($outcome, ModelNotFoundException::class);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        $this->assertSame([], Storage::disk('public')->allFiles("product-images/{$product->id}"));
    }

    public function test_product_deletion_waiting_on_an_upload_removes_the_committed_image_file(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create();
        Storage::disk('public')->deleteDirectory("product-images/{$product->id}");
        $upload = UploadedFile::fake()->createWithContent('before-delete.jpg', 'image committed before delete');

        $outcome = $this->runCompetingTransactions(
            'upload-delete',
            fn () => app(ProductImageManagementService::class)->create($actor, $product, $upload, []),
            'delete-product',
            ['actor_id' => $actor->id, 'product_id' => $product->id],
        );

        $this->assertOutcomeOk($outcome);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        $this->assertSame([], Storage::disk('public')->allFiles("product-images/{$product->id}"));
    }

    public function test_competing_reverse_relation_replacements_preserve_one_direction(): void
    {
        $actor = User::factory()->create();
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        $outcome = $this->runCompetingTransactions(
            'reverse-relation',
            fn () => app(ProductRelationManagementService::class)->replace($actor, $first, [
                ['related_product_id' => $second->id, 'type' => 'related'],
            ]),
            'reverse-relation',
            ['actor_id' => $actor->id, 'product_id' => $second->id, 'related_product_id' => $first->id],
        );

        $this->assertThrowableIs($outcome, ValidationException::class);
        $this->assertDatabaseHas('product_relations', [
            'product_id' => $first->id,
            'related_product_id' => $second->id,
        ]);
        $this->assertDatabaseMissing('product_relations', [
            'product_id' => $second->id,
            'related_product_id' => $first->id,
        ]);
        $this->assertSame(1, ProductRelation::query()
            ->whereIn('product_id', [$first->id, $second->id])
            ->whereIn('related_product_id', [$first->id, $second->id])
            ->count());
    }

    public function test_competing_category_moves_cannot_create_a_cycle(): void
    {
        $actor = User::factory()->create();
        $first = Category::factory()->create();
        $second = Category::factory()->create();

        $outcome = $this->runCompetingTransactions(
            'category-cycle',
            fn () => app(CategoryManagementService::class)->update($actor, $first, ['parent_id' => $second->id]),
            'category-move',
            ['actor_id' => $actor->id, 'category_id' => $second->id, 'parent_id' => $first->id],
        );

        $this->assertThrowableIs($outcome, ValidationException::class);
        $this->assertSame($second->id, $first->fresh()->parent_id);
        $this->assertNull($second->fresh()->parent_id);
    }

    public function test_child_cannot_be_attached_while_parent_eligibility_is_removed(): void
    {
        $actor = User::factory()->create();
        $parent = Category::factory()->create(['is_parent' => true]);
        $child = Category::factory()->create();

        $outcome = $this->runCompetingTransactions(
            'parent-eligibility',
            fn () => app(CategoryManagementService::class)->update($actor, $parent, ['is_parent' => false]),
            'category-move',
            ['actor_id' => $actor->id, 'category_id' => $child->id, 'parent_id' => $parent->id],
        );

        $this->assertThrowableIs($outcome, ValidationException::class);
        $this->assertFalse($parent->fresh()->is_parent);
        $this->assertNull($child->fresh()->parent_id);
    }

    /**
     * @param  Closure(): mixed  $firstOperation
     * @param  array<string, mixed>  $competingPayload
     * @param  (Closure(): mixed)|null  $afterCompetitorBlocks
     * @return array{status: string, class?: string, message?: string}
     */
    private function runCompetingTransactions(
        string $label,
        Closure $firstOperation,
        string $competingOperation,
        array $competingPayload,
        ?Closure $afterCompetitorBlocks = null,
    ): array {
        DB::beginTransaction();
        $process = null;

        try {
            $firstOperation();
            $process = $this->startCompetitor($label, $competingOperation, $competingPayload);
            $this->assertCompetitorWaitsForLock($process['application_name']);
            $afterCompetitorBlocks?->__invoke();
            DB::commit();

            return $this->finishCompetitor($process);
        } catch (Throwable $exception) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($process !== null) {
                $this->stopCompetitor($process);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{process: Process, application_name: string}
     */
    private function startCompetitor(string $label, string $operation, array $payload): array
    {
        $applicationName = sprintf('catalog-%s-%s', $label, bin2hex(random_bytes(4)));
        $payload['application_name'] = $applicationName;
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/catalog_concurrency_worker.php'),
            $operation,
            base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
        $process->setTimeout(15);
        $process->start();

        $deadline = microtime(true) + 5;
        while (! str_contains($process->getOutput(), "ready\n")) {
            if (! $process->isRunning()) {
                throw new RuntimeException('The competing process stopped before initialization: '.$process->getErrorOutput());
            }
            if (microtime(true) >= $deadline) {
                $process->stop();
                throw new RuntimeException('The competing process did not initialize its PostgreSQL connection.');
            }
            usleep(20_000);
        }

        return ['process' => $process, 'application_name' => $applicationName];
    }

    private function assertCompetitorWaitsForLock(string $applicationName): void
    {
        $deadline = microtime(true) + 5;

        do {
            $activity = DB::selectOne(
                'select state, wait_event_type, wait_event from pg_stat_activity where application_name = ?',
                [$applicationName],
            );

            if ($activity?->wait_event_type === 'Lock') {
                return;
            }

            usleep(20_000);
        } while (microtime(true) < $deadline);

        $this->fail("The competing PostgreSQL transaction did not wait for a lock ({$applicationName}).");
    }

    /**
     * @param  array{process: Process, application_name: string}  $process
     * @return array{status: string, class?: string, message?: string}
     */
    private function finishCompetitor(array $process): array
    {
        $process['process']->wait();
        $this->assertTrue($process['process']->isSuccessful(), $process['process']->getErrorOutput());
        $lines = array_values(array_filter(explode("\n", trim($process['process']->getOutput()))));
        $encodedOutcome = end($lines);

        if (! is_string($encodedOutcome) || $encodedOutcome === 'ready') {
            throw new RuntimeException('The competing process did not return a test outcome.');
        }

        /** @var array{status: string, class?: string, message?: string} $outcome */
        $outcome = json_decode($encodedOutcome, true, flags: JSON_THROW_ON_ERROR);

        return $outcome;
    }

    /** @param array{process: Process, application_name: string} $process */
    private function stopCompetitor(array $process): void
    {
        $process['process']->stop(1);
    }

    /** @param array{status: string, class?: string, message?: string} $outcome */
    private function assertThrowableIs(array $outcome, string $expectedClass): void
    {
        $this->assertSame('error', $outcome['status'], 'The competing transaction unexpectedly succeeded.');
        $this->assertArrayHasKey('class', $outcome);
        $this->assertTrue(
            is_a($outcome['class'], $expectedClass, true),
            sprintf('Expected %s, got %s: %s', $expectedClass, $outcome['class'], $outcome['message'] ?? ''),
        );
    }

    /** @param array{status: string, class?: string, message?: string} $outcome */
    private function assertOutcomeOk(array $outcome): void
    {
        $this->assertSame('ok', $outcome['status'], $outcome['message'] ?? 'The competing transaction failed.');
    }
}
