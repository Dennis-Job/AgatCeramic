<?php

namespace Tests\Feature\Api;

use App\Jobs\DeleteStoredFile;
use App\Jobs\ProcessProductImageImport;
use App\Models\AuditLog;
use App\Models\ImportDispatchTask;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageImport;
use App\Models\Role;
use App\Models\StorageCleanupTask;
use App\Models\User;
use App\Services\ImportLifecycleService;
use App\Services\ProductImageImportService;
use App\Services\StorageCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ProductImageImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_and_imports_images_by_sku_while_replacing_matching_ordinal(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $oldDisk = Storage::fake('archive');
        Queue::fake();
        $actor = $this->actor();
        $product = Product::factory()->create(['sku' => '6000011']);
        $oldPath = "product-images/{$product->id}/6000011_1.jpg";
        $oldDisk->put($oldPath, 'original');
        ProductImage::query()->create(['product_id' => $product->id, 'disk' => 'archive', 'path' => $oldPath, 'mime_type' => 'image/jpeg', 'size' => 8, 'is_primary' => true, 'sort_order' => 1]);
        $zip = $this->zip(['6000011/6000011_1.png' => $this->png(), '6000011/6000011_2.png' => $this->png()]);
        $response = $this->actingAs($actor)->post('/api/v1/admin/product-image-imports', ['file' => new UploadedFile($zip, 'images.zip', 'application/zip', null, true)]);
        $response->assertAccepted();
        Queue::assertPushed(ProcessProductImageImport::class);
        $import = ProductImageImport::query()->findOrFail($response->json('data.id'));
        $this->assertDatabaseHas('import_dispatch_tasks', [
            'import_type' => ImportDispatchTask::TYPE_PRODUCT_IMAGE,
            'import_id' => $import->id,
            'status' => 'dispatched',
        ]);
        app(ProductImageImportService::class)->process($import->load('user'));
        $images = $product->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        foreach ($images as $index => $image) {
            $ordinal = $index + 1;
            $this->assertSame("6000011_{$ordinal}-i{$import->id}.png", basename($image->path));
            $this->assertSame($ordinal === 1, $image->is_primary);
            $this->assertSame('public', $image->disk);
            Storage::disk('public')->assertExists($image->path);
        }
        $this->assertDatabaseHas('storage_cleanup_tasks', ['disk' => 'archive', 'path' => $oldPath]);
    }

    public function test_invalid_sku_folder_is_isolated_from_valid_folder(): void
    {
        Storage::fake('public');
        $actor = $this->actor();
        $product = Product::factory()->create(['sku' => '6000011']);
        $zip = $this->zip(['6000011/6000011_1.png' => $this->png(), 'unknown/unknown_1.png' => $this->png()]);
        Storage::disk('local')->put('product-image-imports/test.zip', file_get_contents($zip));
        $import = ProductImageImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'test.zip', 'disk' => 'local', 'path' => 'product-image-imports/test.zip', 'status' => 'pending']);
        app(ProductImageImportService::class)->process($import->load('user'));
        $this->assertSame(1, $import->refresh()->failed_folders);
        $this->assertSame(1, $import->created_images);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id]);
    }

    public function test_mac_os_metadata_is_ignored_when_importing_a_sku_gallery(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        $actor = $this->actor();
        $product = Product::factory()->create(['sku' => '6000011']);
        $zip = $this->zip([
            '6000011/' => '',
            '6000011/6000011_1.png' => $this->png(),
            '__MACOSX/' => '',
            '__MACOSX/6000011/' => '',
            '__MACOSX/6000011/._6000011_1.png' => 'AppleDouble metadata',
            '.DS_Store' => 'Finder metadata',
            '6000011/.DS_Store' => 'Finder metadata',
            '._6000011' => 'AppleDouble metadata',
            '6000011/._6000011_1.png' => 'AppleDouble metadata',
        ]);
        try {
            $response = $this->actingAs($actor)->post('/api/v1/admin/product-image-imports', ['file' => new UploadedFile($zip, 'images.zip', 'application/zip', null, true)]);
            $response->assertAccepted();
            $import = ProductImageImport::query()->findOrFail($response->json('data.id'));
            $this->assertTrue(app(ProductImageImportService::class)->process($import->load('user')));
            $import->refresh();
            $this->assertSame(1, $import->total_folders);
            $this->assertSame(1, $import->processed_folders);
            $this->assertSame(1, $import->created_images);
            $this->assertSame(0, $import->failed_folders);
            $this->assertSame(['6000011'], $import->processed_skus);
            $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'is_primary' => true]);
            $this->assertDatabaseCount('product_image_import_errors', 0);
        } finally {
            @unlink($zip);
        }
    }

    public function test_an_archive_containing_only_mac_os_metadata_is_rejected(): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $zip = $this->zip(['__MACOSX/' => '', '__MACOSX/._6000011' => 'metadata', '.DS_Store' => 'metadata']);
        try {
            Storage::disk('local')->put('product-image-imports/metadata.zip', file_get_contents($zip));
            $import = ProductImageImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'metadata.zip', 'disk' => 'local', 'path' => 'product-image-imports/metadata.zip', 'status' => 'pending']);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('ZIP-архив не содержит папок SKU.');
            app(ProductImageImportService::class)->process($import->load('user'));
        } finally {
            @unlink($zip);
        }
    }

    #[DataProvider('unsupportedArchiveEntries')]
    public function test_archive_safety_checks_still_reject_unsupported_entries(string $name, int $size, bool $symlink, string $message): void
    {
        Storage::fake('local');
        $actor = $this->actor();
        $path = $this->zip([$name => str_repeat('x', $size)]);
        try {
            if ($symlink) {
                $zip = new ZipArchive;
                $zip->open($path);
                $zip->setExternalAttributesIndex(0, ZipArchive::OPSYS_UNIX, 0120777 << 16);
                $zip->close();
            }
            Storage::disk('local')->put('product-image-imports/metadata.zip', file_get_contents($path));
            $import = ProductImageImport::query()->create(['user_id' => $actor->id, 'original_filename' => 'metadata.zip', 'disk' => 'local', 'path' => 'product-image-imports/metadata.zip', 'status' => 'pending']);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($message);
            app(ProductImageImportService::class)->process($import->load('user'));
        } finally {
            @unlink($path);
        }
    }

    /** @return array<string, array{string, int, bool, string}> */
    public static function unsupportedArchiveEntries(): array
    {
        return [
            'traversal' => ['__MACOSX/../._photo.png', 1, false, 'Архив содержит небезопасный путь.'],
            'absolute path' => ['/.DS_Store', 1, false, 'Архив содержит небезопасный путь.'],
            'backslash' => ['__MACOSX\\._photo.png', 1, false, 'Архив содержит небезопасный путь.'],
            'symbolic link' => ['__MACOSX/6000011/._photo.png', 1, true, 'Архив не может содержать символические ссылки.'],
            'oversized metadata' => ['__MACOSX/6000011/._photo.png', 10 * 1024 * 1024 + 1, false, 'Размер изображения в архиве превышает 10 МБ.'],
            'nested real file' => ['6000011/nested/6000011_1.png', 1, false, 'Файлы должны находиться непосредственно в папке SKU.'],
        ];
    }

    public function test_delayed_cleanup_of_a_replaced_image_cannot_delete_a_later_generation(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $actor = $this->actor();
        $product = Product::factory()->create(['sku' => '6000011']);
        $oldPath = "product-images/{$product->id}/6000011_1.jpg";
        Storage::disk('public')->put($oldPath, 'old-jpg');
        ProductImage::query()->create(['product_id' => $product->id, 'disk' => 'public', 'path' => $oldPath, 'mime_type' => 'image/jpeg', 'size' => 7, 'is_primary' => true, 'sort_order' => 1]);

        foreach (['png' => $this->png(), 'jpg' => $this->jpg()] as $extension => $image) {
            $archive = $this->zip(["6000011/6000011_1.{$extension}" => $image]);
            $path = "product-image-imports/{$extension}.zip";
            Storage::disk('local')->put($path, file_get_contents($archive));
            @unlink($archive);
            $import = ProductImageImport::query()->create(['user_id' => $actor->id, 'original_filename' => "{$extension}.zip", 'disk' => 'local', 'path' => $path, 'status' => 'pending']);
            app(ProductImageImportService::class)->process($import->load('user'));
        }

        $current = ProductImage::query()->where('product_id', $product->id)->sole();
        StorageCleanupTask::query()->where('disk', 'public')->get()->each(fn (StorageCleanupTask $task) => (new DeleteStoredFile($task->id))->handle());
        Storage::disk('public')->assertExists($current->path);
        $this->assertMatchesRegularExpression('#6000011_1-i\\d+\\.jpg$#', $current->path);
    }

    public function test_failed_public_write_keeps_the_gallery_and_reclaims_even_a_partial_write(): void
    {
        $disk = Storage::fake('public');
        Queue::fake();
        $product = Product::factory()->create(['sku' => '6000011']);
        $old = $this->existingImage($product);
        $import = $this->storedImport(['6000011/6000011_1.png' => $this->png(), '6000011/6000011_2.png' => $this->png()]);
        $level = DB::transactionLevel();
        $written = [];
        $faulty = Mockery::mock($disk)->makePartial();
        $faulty->shouldReceive('put')->twice()->andReturnUsing(function (string $path, string $contents) use ($disk, $level, &$written): bool {
            $this->assertSame($level, DB::transactionLevel());
            $this->assertDatabaseHas('storage_cleanup_tasks', ['disk' => 'public', 'path' => $path, 'status' => 'pending']);
            $this->assertTrue($disk->put($path, $contents));
            $written[] = $path;

            return count($written) === 1;
        });
        Storage::set('public', $faulty);

        $this->assertTrue(app(ProductImageImportService::class)->process($import));
        Storage::set('public', $disk);
        $this->assertSame(1, $import->refresh()->failed_folders);
        $this->assertSame($old->path, $old->fresh()->path);
        $this->assertSame(0, $import->created_images + $import->replaced_images);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'product.image-imported']);
        $this->assertCount(2, $written);
        foreach ($written as $path) {
            $task = StorageCleanupTask::query()->where('path', $path)->sole();
            (new DeleteStoredFile($task->id))->handle();
            $disk->assertMissing($path);
        }
        $this->assertSame('original', $disk->get($old->path));
    }

    public function test_rollback_preserves_old_bytes_and_cleanup_retries_a_delete_failure(): void
    {
        $disk = Storage::fake('public');
        Queue::fake();
        $product = Product::factory()->create(['sku' => '6000011']);
        $old = $this->existingImage($product);
        $import = $this->storedImport(['6000011/6000011_1.png' => $this->png()]);
        $newPath = null;
        Event::listen('eloquent.creating: '.AuditLog::class, function () use ($product, &$newPath): never {
            $newPath = $product->images()->sole()->path;
            throw new RuntimeException('Injected failure before commit.');
        });

        $this->assertTrue(app(ProductImageImportService::class)->process($import));
        $this->assertSame(1, $import->refresh()->failed_folders);
        $this->assertSame(0, $import->replaced_images);
        $this->assertSame($old->path, $old->fresh()->path);
        $this->assertDatabaseMissing('storage_cleanup_tasks', ['path' => $old->path]);
        $this->assertIsString($newPath);
        $task = StorageCleanupTask::query()->where('path', $newPath)->sole();
        $job = new DeleteStoredFile($task->id);
        $faulty = Mockery::mock($disk)->makePartial();
        $faulty->shouldReceive('delete')->with($newPath)->once()->andReturnFalse();
        Storage::set('public', $faulty);
        try {
            $job->handle();
            $this->fail('Expected cleanup to report the storage failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('did not delete', $exception->getMessage());
        } finally {
            Storage::set('public', $disk);
        }

        $this->assertSame('pending', $task->fresh()->status);
        $disk->assertExists($newPath);
        $this->travel(11)->minutes();
        $this->artisan('storage-cleanup:retry')->assertSuccessful();
        Queue::assertPushed(DeleteStoredFile::class, fn (DeleteStoredFile $queued): bool => $queued->cleanupTaskId === $task->id);
        $job->handle();
        $disk->assertMissing($newPath);
        $this->assertSame('original', $disk->get($old->path));
        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_error_after_commit_and_retry_keep_the_published_file_and_checkpoint(): void
    {
        $disk = Storage::fake('public');
        Queue::fake();
        $product = Product::factory()->create(['sku' => '6000011']);
        $old = $this->existingImage($product);
        $import = $this->storedImport(['6000011/6000011_1.png' => $this->png()]);
        $stale = $import->fresh();
        Event::listen('eloquent.creating: '.AuditLog::class, static function (): void {
            DB::afterCommit(static function (): never {
                throw new RuntimeException('Injected failure after commit.');
            });
        });

        $service = app(ProductImageImportService::class);
        $this->assertTrue($service->process($import));
        $published = $old->fresh()->path;
        $this->assertNotSame($old->path, $published);
        $this->assertTrue($service->process($stale));
        $this->assertSame($published, $old->fresh()->path);
        $this->assertSame([1, 1, 0, ['6000011']], [$import->refresh()->processed_folders, $import->replaced_images, $import->failed_folders, $import->processed_skus]);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseMissing('storage_cleanup_tasks', ['path' => $published]);
        (new DeleteStoredFile(StorageCleanupTask::query()->where('path', $old->path)->sole()->id))->handle();
        $disk->assertMissing($old->path);
        $this->assertSame($this->png(), $disk->get($published));
    }

    public function test_import_resumes_a_full_chunk_using_persisted_checkpoints(): void
    {
        Storage::fake('public');
        Queue::fake();
        $files = [];
        foreach (range(6000001, 6000021) as $sku) {
            Product::factory()->create(['sku' => (string) $sku]);
            $files["{$sku}/{$sku}_1.png"] = $this->png();
        }
        $import = $this->storedImport($files);
        $stale = $import->fresh();
        $service = app(ProductImageImportService::class);

        $this->assertFalse($service->process($import));
        $this->assertSame(20, $import->refresh()->processed_folders);
        $this->assertTrue($service->process($stale));
        $this->assertTrue($service->process($import));
        $this->assertSame(21, $import->refresh()->created_images);
        $this->assertSame(0, $import->replaced_images);
        $this->assertSame(array_map('strval', range(6000001, 6000021)), $import->processed_skus);
        $this->assertDatabaseCount('audit_logs', 21);
    }

    public function test_late_worker_cannot_reopen_a_completed_import_dispatch(): void
    {
        Queue::fake();
        $import = $this->storedImport(['6000011/6000011_1.png' => $this->png()]);
        $stale = $import->fresh();
        $dispatch = ImportDispatchTask::query()->create([
            'import_type' => ImportDispatchTask::TYPE_PRODUCT_IMAGE,
            'import_id' => $import->id,
            'status' => 'dispatched',
        ]);
        $lifecycle = app(ImportLifecycleService::class);
        $lifecycle->completeProductImageImport($import);
        $lifecycle->continueProductImageImport($stale);

        $this->assertSame('completed', $dispatch->refresh()->status);
        Queue::assertNotPushed(ProcessProductImageImport::class);
    }

    public function test_overlapping_attempts_publish_only_the_first_committed_generation(): void
    {
        $disk = Storage::fake('public');
        Queue::fake();
        $product = Product::factory()->create(['sku' => '6000011']);
        $import = $this->storedImport(['6000011/6000011_1.png' => $this->png()]);
        $service = app(ProductImageImportService::class);
        $other = $import->fresh();
        $firstPath = null;
        $interleaved = Mockery::mock($disk)->makePartial();
        $interleaved->shouldReceive('put')->twice()->andReturnUsing(function (string $path, string $data) use ($disk, $service, $other, &$firstPath): bool {
            if ($firstPath === null) {
                $firstPath = $path;
                $this->assertTrue($service->process($other));
            }

            return $disk->put($path, $data);
        });
        Storage::set('public', $interleaved);
        $this->assertTrue($service->process($import));
        Storage::set('public', $disk);

        $published = $product->images()->sole();
        $this->assertNotSame($firstPath, $published->path);
        $this->assertSame([1, 1, 0], [$import->refresh()->processed_folders, $import->created_images, $import->replaced_images]);
        $this->assertDatabaseCount('audit_logs', 1);
        $task = StorageCleanupTask::query()->sole();
        $this->assertSame($firstPath, $task->path);
        (new DeleteStoredFile($task->id))->handle();
        $this->assertSame([$published->path], $disk->allFiles());
    }

    public function test_scheduler_reclaims_abandoned_files_only_after_the_reservation_expires(): void
    {
        $disk = Storage::fake('public');
        Queue::fake();
        $path = 'product-images/1/unfinished.png';
        $id = app(StorageCleanupService::class)->reserve('public', $path, now()->addMinutes(10));
        $disk->put($path, $this->png());

        $this->artisan('storage-cleanup:retry')->assertSuccessful();
        Queue::assertNotPushed(DeleteStoredFile::class);
        $disk->assertExists($path);

        $this->travel(10)->minutes();
        $this->artisan('storage-cleanup:retry')->assertSuccessful();
        Queue::assertPushed(DeleteStoredFile::class, fn (DeleteStoredFile $job): bool => $job->cleanupTaskId === $id);
        (new DeleteStoredFile($id))->handle();
        $disk->assertMissing($path);
    }

    private function existingImage(Product $product): ProductImage
    {
        $path = "product-images/{$product->id}/{$product->sku}_1.jpg";
        Storage::disk('public')->put($path, 'original');

        return $product->images()->create(['disk' => 'public', 'path' => $path, 'mime_type' => 'image/jpeg', 'size' => 8, 'is_primary' => true, 'sort_order' => 1]);
    }

    /** @param array<string, string> $files */
    private function storedImport(array $files): ProductImageImport
    {
        $disk = Storage::fake('local');
        $archive = $this->zip($files);
        $path = 'product-image-imports/test.zip';
        try {
            $disk->put($path, file_get_contents($archive));
        } finally {
            unlink($archive);
        }

        return ProductImageImport::query()->create(['user_id' => $this->actor()->id, 'original_filename' => 'test.zip', 'disk' => 'local', 'path' => $path, 'status' => 'pending']);
    }

    private function actor(): User
    {
        $permission = Permission::query()->firstOrCreate(['code' => 'imports.manage'], ['name' => 'Import', 'description' => '']);
        $role = Role::factory()->create();
        $role->permissions()->sync([$permission->id]);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        } $zip->close();

        return $path;
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLdeAAAAABJRU5ErkJggg==');
    }

    private function jpg(): string
    {
        return base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQL/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/Aaf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/Aaf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Ap//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/If/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//Z');
    }
}
