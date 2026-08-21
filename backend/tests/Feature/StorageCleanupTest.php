<?php

namespace Tests\Feature;

use App\Jobs\DeleteStoredFile;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\StorageCleanupTask;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StorageCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_deletion_durably_queues_every_file_before_removing_database_rows(): void
    {
        Queue::fake();
        Storage::fake('public');
        Storage::fake('archive');
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();
        $files = [
            ['disk' => 'public', 'path' => "product-images/{$product->id}/main.jpg"],
            ['disk' => 'archive', 'path' => "product-images/{$product->id}/detail.webp"],
        ];

        foreach ($files as $index => $file) {
            Storage::disk($file['disk'])->put($file['path'], 'image');
            ProductImage::query()->create([
                'product_id' => $product->id,
                ...$file,
                'mime_type' => $index === 0 ? 'image/jpeg' : 'image/webp',
                'size' => 5,
                'is_primary' => $index === 0,
            ]);
        }

        $this->actingAs($actor)->deleteJson("/api/v1/admin/products/{$product->id}")->assertNoContent();

        $this->assertDatabaseCount('storage_cleanup_tasks', 2);
        Queue::assertPushed(DeleteStoredFile::class, 2);
        foreach ($files as $file) {
            Storage::disk($file['disk'])->assertExists($file['path']);
            $task = StorageCleanupTask::query()->where($file)->sole();
            (new DeleteStoredFile($task->id))->handle();
            Storage::disk($file['disk'])->assertMissing($file['path']);
        }
        $this->assertDatabaseCount('storage_cleanup_tasks', 2);
        $this->assertSame(2, StorageCleanupTask::query()->where('status', 'completed')->count());
    }

    public function test_failed_deletion_remains_visible_and_can_be_redispatched(): void
    {
        $task = StorageCleanupTask::query()->create([
            'disk' => 'broken',
            'path' => 'product-images/1/orphan.jpg',
        ]);
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('delete')->once()->with($task->path)->andThrow(new RuntimeException('disk unavailable'));
        Storage::shouldReceive('disk')->once()->with('broken')->andReturn($filesystem);
        $job = new DeleteStoredFile($task->id);

        try {
            $job->handle();
            $this->fail('The cleanup job should report a storage failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('disk unavailable', $exception->getMessage());
        }

        $task->refresh();
        $this->assertSame('pending', $task->status);
        $this->assertSame(1, $task->attempts);
        $this->assertSame('disk unavailable', $task->last_error);

        $job->failed(new RuntimeException('disk unavailable'));
        $this->assertDatabaseHas('storage_cleanup_tasks', ['id' => $task->id, 'status' => 'failed']);

        $this->travel(16)->minutes();
        Queue::fake();
        $this->artisan('storage-cleanup:retry', ['--limit' => 10])
            ->expectsOutput('Dispatched 1 storage cleanup task(s).')
            ->assertSuccessful();
        Queue::assertPushed(DeleteStoredFile::class, fn (DeleteStoredFile $queued): bool => $queued->cleanupTaskId === $task->id);
    }

    public function test_cleanup_job_is_idempotent_after_completion(): void
    {
        Storage::fake('public');
        $task = StorageCleanupTask::query()->create([
            'disk' => 'public',
            'path' => 'product-images/1/already-deleted.jpg',
        ]);
        $job = new DeleteStoredFile($task->id);

        $job->handle();
        $job->handle();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(1, $task->attempts);
        $this->assertNotNull($task->completed_at);
    }

    private function userWithRole(string $slug): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
