<?php

namespace Tests\Integration;

use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\ProductImageImport;
use App\Models\ProductRelation;
use App\Models\Role;
use App\Models\StorageCleanupTask;
use App\Models\User;
use App\Services\CategoryManagementService;
use App\Services\ProductCollectionManagementService;
use App\Services\ProductGroupManagementService;
use App\Services\ProductImageImportService;
use App\Services\ProductImageManagementService;
use App\Services\ProductManagementService;
use App\Services\ProductRelationManagementService;
use Closure;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;
use ZipArchive;

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

    #[DataProvider('competingGroupChanges')]
    public function test_product_group_composition_stays_consistent_during_product_edit(
        string $field,
        bool $existingGroup,
        bool $groupWins,
    ): void {
        $actor = User::factory()->create();
        $category = Category::factory()->create();
        $targetCategory = Category::factory()->create();
        $brand = Brand::factory()->create();
        $axis = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach($axis->id);
        $targetCategory->attributes()->attach($axis->id);

        $products = Product::factory()->count(3)->create(['category_id' => $category->id, 'brand_id' => $brand->id]);
        foreach ($products as $product) {
            $product->attributeValues()->create(['attribute_id' => $axis->id, 'value' => (string) $product->id]);
        }
        [$product, $firstMember, $secondMember] = $products->all();
        $initialMembers = [$firstMember->id, $secondMember->id];
        $originalGroup = [
            'code' => "A066-{$product->id}",
            'name' => 'Original group',
            'axis_attribute_ids' => [$axis->id],
            'product_ids' => $initialMembers,
        ];
        $groups = app(ProductGroupManagementService::class);
        $group = $existingGroup ? $groups->create($actor, $originalGroup) : null;
        $desiredGroup = [...$originalGroup, 'name' => 'Expanded group', 'product_ids' => [$product->id, ...$initialMembers]];
        $productChanges = [$field => $field === 'category_id' ? $targetCategory->id : null];

        if ($groupWins) {
            $outcome = $this->runCompetingTransactions(
                'group-first',
                fn () => $group === null ? $groups->create($actor, $desiredGroup) : $groups->update($actor, $group, $desiredGroup),
                'product-update',
                ['actor_id' => $actor->id, 'product_id' => $product->id, 'attributes' => $productChanges],
            );
        } else {
            $outcome = $this->runCompetingTransactions(
                'product-first',
                fn () => app(ProductManagementService::class)->update($actor, $product, $productChanges),
                'group-write',
                ['actor_id' => $actor->id, 'group_id' => $group?->id, 'group' => $desiredGroup],
            );
        }

        $this->assertThrowableIs($outcome, ValidationException::class);
        $this->assertSame([$groupWins ? $field : 'product_ids'], $outcome['fields'] ?? []);
        $product->refresh();
        $expectedCategory = ! $groupWins && $field === 'category_id' ? $targetCategory->id : $category->id;
        $expectedBrand = ! $groupWins && $field === 'brand_id' ? null : $brand->id;
        $this->assertSame($expectedCategory, $product->category_id);
        $this->assertSame($expectedBrand, $product->brand_id);

        if (! $groupWins && ! $existingGroup) {
            $this->assertDatabaseMissing('product_groups', ['code' => $desiredGroup['code']]);
        } else {
            $savedGroup = ProductGroup::query()->where('code', $desiredGroup['code'])->firstOrFail();
            $this->assertSame($groupWins ? $desiredGroup['name'] : $originalGroup['name'], $savedGroup->name);
            $this->assertEqualsCanonicalizing(
                $groupWins ? $desiredGroup['product_ids'] : $initialMembers,
                $savedGroup->products()->pluck('products.id')->all(),
            );
        }
        if (! $groupWins) {
            $this->assertDatabaseMissing('product_group_members', ['product_id' => $product->id]);
        }
    }

    public static function competingGroupChanges(): iterable
    {
        foreach (['category_id', 'brand_id'] as $field) {
            foreach (['create' => false, 'update' => true] as $operation => $existingGroup) {
                yield "$field vs group $operation, group first" => [$field, $existingGroup, true];
                yield "$field vs group $operation, product first" => [$field, $existingGroup, false];
            }
        }
    }

    public function test_collection_assignment_waiting_for_category_disable_is_rejected(): void
    {
        $actor = User::factory()->create();
        $collection = ProductCollection::factory()->create();
        $category = Category::factory()->create(['uses_collections' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $collection->brand_id]);
        $outcome = $this->runCompetingTransactions(
            'disable-collections',
            fn () => app(CategoryManagementService::class)->update($actor, $category, ['uses_collections' => false]),
            'collection-assignment',
            ['actor_id' => $actor->id, 'product_id' => $product->id, 'collection_id' => $collection->id],
        );
        $this->assertThrowableIs($outcome, ValidationException::class);
        $this->assertNull($product->refresh()->collection_id);
    }

    public function test_category_disable_waiting_for_collection_assignment_is_rejected(): void
    {
        $actor = User::factory()->create();
        $collection = ProductCollection::factory()->create();
        $category = Category::factory()->create(['uses_collections' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $collection->brand_id]);
        $outcome = $this->runCompetingTransactions(
            'assign-collection',
            fn () => app(ProductManagementService::class)->update($actor, $product, ['collection_id' => $collection->id]),
            'collections-disable',
            ['actor_id' => $actor->id, 'category_id' => $category->id],
        );
        $this->assertThrowableIs($outcome, ValidationException::class);
        $this->assertTrue($category->refresh()->uses_collections);
        $this->assertSame($collection->id, $product->refresh()->collection_id);
    }

    public function test_collection_assignment_waiting_for_brand_change_is_rejected(): void
    {
        $actor = User::factory()->create();
        $collection = ProductCollection::factory()->create();
        $category = Category::factory()->create(['uses_collections' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $collection->brand_id]);
        $targetBrand = Brand::factory()->create();
        $outcome = $this->runCompetingTransactions(
            'collection-brand',
            fn () => app(ProductCollectionManagementService::class)->update($actor, $collection, ['brand_id' => $targetBrand->id]),
            'collection-assignment',
            ['actor_id' => $actor->id, 'product_id' => $product->id, 'collection_id' => $collection->id],
        );
        $this->assertThrowableIs($outcome, ValidationException::class);
        $this->assertNull($product->refresh()->collection_id);
    }

    public function test_collection_assignment_waiting_for_collection_deactivation_is_rejected(): void
    {
        $actor = User::factory()->create();
        $collection = ProductCollection::factory()->create();
        $category = Category::factory()->create(['uses_collections' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $collection->brand_id]);
        $outcome = $this->runCompetingTransactions(
            'collection-inactive',
            fn () => app(ProductCollectionManagementService::class)->update($actor, $collection, ['is_active' => false]),
            'collection-assignment',
            ['actor_id' => $actor->id, 'product_id' => $product->id, 'collection_id' => $collection->id],
        );
        $this->assertThrowableIs($outcome, ValidationException::class);
        $this->assertNull($product->refresh()->collection_id);
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

    public function test_image_import_preserves_the_gallery_when_postgres_rejects_commit(): void
    {
        $local = Storage::fake('local');
        $public = Storage::fake('public');
        $product = Product::factory()->create();
        $originalPath = "product-images/{$product->id}/{$product->sku}_1.jpg";
        $public->put($originalPath, 'original');
        $image = $product->images()->create(['disk' => 'public', 'path' => $originalPath, 'mime_type' => 'image/jpeg', 'size' => 8, 'sort_order' => 1, 'is_primary' => true]);
        $import = ProductImageImport::query()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'local',
            'path' => 'images.zip',
            'original_filename' => 'images.zip',
            'status' => 'pending',
        ]);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($local->path($import->path), ZipArchive::CREATE));
        $zip->addFromString("{$product->sku}/{$product->sku}_1.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4WQAAAAASUVORK5CYII='));
        $zip->close();

        DB::statement('CREATE TEMP TABLE a071_parent (id int PRIMARY KEY)');
        DB::statement('CREATE TEMP TABLE a071_child (parent_id int REFERENCES a071_parent(id) DEFERRABLE INITIALLY DEFERRED)');
        $newPath = null;
        Event::listen('eloquent.creating: '.AuditLog::class, function () use ($product, &$newPath): void {
            $newPath = $product->images()->sole()->path;
            DB::statement('INSERT INTO a071_child VALUES (1)');
        });

        $this->assertTrue(app(ProductImageImportService::class)->process($import));
        $this->assertSame($originalPath, $image->refresh()->path);
        $this->assertSame('original', $public->get($originalPath));
        $this->assertSame([1, 0, 1], [$import->refresh()->processed_folders, $import->replaced_images, $import->failed_folders]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'product.image-imported', 'entity_id' => $product->id]);
        $this->assertDatabaseMissing('storage_cleanup_tasks', ['path' => $originalPath]);
        $this->assertIsString($newPath);
        $this->assertSame('completed', StorageCleanupTask::query()->where('path', $newPath)->sole()->status);
        $public->assertMissing($newPath);
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

    #[DataProvider('contentWriters')]
    public function test_url_only_content_writers_wait_for_media_cleanup_lock(string $operation): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Queue::fake();
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());
        $image = Media::query()->create([
            'kind' => 'image', 'disk' => 'public', 'path' => 'media/concurrency-'.bin2hex(random_bytes(8)).'.png',
            'mime_type' => 'image/png', 'size' => 5, 'title' => 'Synthetic shared image',
        ]);
        $category = Category::factory()->create(['image_id' => $image->id]);
        $url = Storage::disk('public')->url($image->path);
        $banner = Banner::query()->create(['title' => 'Concurrency URL']);
        $page = Page::query()->create([
            'title' => 'Concurrency URL', 'slug' => 'concurrency-'.bin2hex(random_bytes(8)), 'body' => '',
            'blocks' => [], 'seo' => $operation === 'page-publish' ? ['og_image_url' => $url] : [],
        ]);
        $outcome = $this->runCompetingTransactions(
            $operation,
            fn () => HomePage::query()->lockForUpdate()->findOrFail(1),
            $operation,
            ['actor_id' => $actor->id, 'banner_id' => $banner->id, 'page_id' => $page->id, 'image_url' => $url],
        );
        $this->assertOutcomeOk($outcome);
        app(CategoryManagementService::class)->update($actor, $category, ['image_id' => null]);
        $this->assertDatabaseHas('media', ['id' => $image->id]);
        Queue::assertNothingPushed();
    }

    public static function contentWriters(): array
    {
        return [['banner-create'], ['banner-update'], ['page-create'], ['page-update'], ['page-publish']];
    }

    /**
     * @param  Closure(): mixed  $firstOperation
     * @param  array<string, mixed>  $competingPayload
     * @param  (Closure(): mixed)|null  $afterCompetitorBlocks
     * @return array{status: string, class?: string, message?: string, fields?: list<string>}
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
                'select pg_backend_pid() = any(pg_blocking_pids(pid)) as blocked_by_parent from pg_stat_activity where application_name = ?',
                [$applicationName],
            );

            if ($activity?->blocked_by_parent === true) {
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

        /** @var array{status: string, class?: string, message?: string, fields?: list<string>} $outcome */
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
