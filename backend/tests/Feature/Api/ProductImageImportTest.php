<?php

namespace Tests\Feature\Api;

use App\Jobs\DeleteStoredFile;
use App\Jobs\ProcessProductImageImport;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageImport;
use App\Models\Role;
use App\Models\StorageCleanupTask;
use App\Models\User;
use App\Services\ProductImageImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ProductImageImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_and_imports_images_by_sku_while_replacing_matching_ordinal(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        $actor = $this->actor();
        $product = Product::factory()->create(['sku' => '6000011']);
        ProductImage::query()->create(['product_id' => $product->id, 'disk' => 'public', 'path' => "product-images/{$product->id}/6000011_1.jpg", 'mime_type' => 'image/jpeg', 'size' => 1, 'is_primary' => true, 'sort_order' => 1]);
        $zip = $this->zip(['6000011/6000011_1.png' => $this->png(), '6000011/6000011_2.png' => $this->png()]);
        $response = $this->actingAs($actor)->post('/api/v1/admin/product-image-imports', ['file' => new UploadedFile($zip, 'images.zip', 'application/zip', null, true)]);
        $response->assertAccepted();
        Queue::assertPushed(ProcessProductImageImport::class);
        $import = ProductImageImport::query()->findOrFail($response->json('data.id'));
        app(ProductImageImportService::class)->process($import->load('user'));
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'path' => "product-images/{$product->id}/6000011_1-i{$import->id}.png", 'is_primary' => true]);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'path' => "product-images/{$product->id}/6000011_2-i{$import->id}.png"]);
        $this->assertSame(2, ProductImage::query()->where('product_id', $product->id)->count());
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
