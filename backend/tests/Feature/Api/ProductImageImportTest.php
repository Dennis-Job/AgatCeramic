<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessProductImageImport;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageImport;
use App\Models\Role;
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
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'path' => "product-images/{$product->id}/6000011_1.png", 'is_primary' => true]);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'path' => "product-images/{$product->id}/6000011_2.png"]);
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
}
