<?php

namespace Tests\Feature\Api;

use App\Jobs\DeleteStoredFile;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StorageCleanupTask;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_media_references_and_document_lifecycle(): void
    {
        Storage::fake('public');
        $actor = $this->userWithRole('catalog-manager');
        $image = $this->actingAs($actor)->post('/api/v1/admin/media', [
            'kind' => 'image', 'title' => 'Логотип', 'alt' => 'Логотип Agat',
            'file' => UploadedFile::fake()->image('logo.png', 400, 300),
        ])->assertCreated()->assertJsonPath('data.width', 400)->json('data.id');
        $document = $this->actingAs($actor)->post('/api/v1/admin/media', [
            'kind' => 'document', 'title' => 'Каталог PDF',
            'file' => UploadedFile::fake()->create('catalog.pdf', 12, 'application/pdf'),
        ])->assertCreated()->json('data.id');

        $brand = $this->actingAs($actor)->postJson('/api/v1/admin/brands', [
            'name' => 'Agat', 'slug' => 'agat', 'logo_id' => $image, 'document_ids' => [$document],
        ])->assertCreated()->assertJsonPath('data.logo.id', $image)->assertJsonPath('data.documents.0.id', $document)->json('data.id');
        $category = $this->actingAs($actor)->postJson('/api/v1/admin/categories', [
            'name' => 'Плитка', 'slug' => 'tile', 'image_id' => $image, 'document_ids' => [$document],
        ])->assertCreated()->assertJsonPath('data.image.id', $image)->json('data.id');

        $this->actingAs($actor)->deleteJson("/api/v1/admin/media/{$image}")->assertUnprocessable();
        $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$brand}", ['logo_id' => null, 'document_ids' => []])->assertOk();
        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$category}", ['image_id' => null, 'document_ids' => []])->assertOk();
        $path = Media::query()->findOrFail($image)->path;
        $thumbnailPath = Media::query()->findOrFail($image)->thumbnail_path;
        Storage::disk('public')->assertExists($thumbnailPath);
        $this->actingAs($actor)->deleteJson("/api/v1/admin/media/{$image}")->assertNoContent();
        $this->actingAs($actor)->deleteJson("/api/v1/admin/media/{$document}")->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $image]);
        $task = StorageCleanupTask::query()->where('path', $path)->sole();
        (new DeleteStoredFile($task->id))->handle();
        Storage::disk('public')->assertMissing($path);
        $thumbnailTask = StorageCleanupTask::query()->where('path', $thumbnailPath)->sole();
        (new DeleteStoredFile($thumbnailTask->id))->handle();
        Storage::disk('public')->assertMissing($thumbnailPath);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.deleted', 'entity_id' => $image]);
    }

    public function test_permissions_and_kind_validation(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/media')->assertForbidden();
        $actor = $this->userWithRole('catalog-manager');
        $this->actingAs($actor)->post('/api/v1/admin/media', [
            'kind' => 'image', 'title' => 'Ошибка',
            'file' => UploadedFile::fake()->create('file.pdf', 12, 'application/pdf'),
        ])->assertUnprocessable();
        $image = Media::query()->create([
            'kind' => 'image', 'disk' => 'public', 'path' => 'media/test.jpg',
            'mime_type' => 'image/jpeg', 'size' => 1, 'title' => 'Фото',
        ]);
        $this->actingAs($actor)->postJson('/api/v1/admin/brands', [
            'name' => 'Bad', 'slug' => 'bad', 'document_ids' => [$image->id],
        ])->assertUnprocessable();
        $this->actingAs($actor)->postJson('/api/v1/admin/brands', [
            'name' => 'Good', 'slug' => 'good', 'logo_id' => $image->id,
        ])->assertCreated();
        $this->actingAs($actor)->getJson('/api/v1/admin/media?kind=bad')->assertUnprocessable();
        $this->assertSame(1, Brand::query()->count());
        $this->assertSame(0, Category::query()->count());

        $contentRole = Role::query()->where('slug', 'content-manager')->sole();
        $contentRole->permissions()->detach(Permission::query()->where('code', 'media.manage')->sole()->id);
        $contentOnly = User::factory()->create();
        $contentOnly->roles()->attach($contentRole);
        $this->actingAs($contentOnly)->getJson('/api/v1/admin/media')->assertOk();
        $this->actingAs($contentOnly)->post('/api/v1/admin/media', [
            'kind' => 'document', 'title' => 'Нет права',
            'file' => UploadedFile::fake()->create('spec.pdf', 12, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_banner_managed_image_can_be_cleared_without_reintroducing_its_url(): void
    {
        $actor = $this->userWithRole('content-manager');
        $image = Media::query()->create([
            'kind' => 'image', 'disk' => 'public', 'path' => 'media/banner.png',
            'mime_type' => 'image/png', 'size' => 1, 'title' => 'Баннер',
        ]);
        $created = $this->actingAs($actor)->postJson('/api/v1/admin/banners', [
            'title' => 'Акция', 'image_media_id' => $image->id,
            'image_url' => 'https://legacy.example.test/banner.png',
            'is_published' => true,
        ])->assertCreated()->assertJsonPath('data.legacy_image_url', null)
            ->assertJsonPath('data.image_media_id', $image->id);
        $id = $created->json('data.id');
        $this->assertStringContainsString('media/banner.png', $created->json('data.image_url'));
        $this->actingAs($actor)->patchJson("/api/v1/admin/banners/{$id}", [
            'image_media_id' => null, 'image_url' => null,
        ])->assertOk()->assertJsonPath('data.image_url', null)
            ->assertJsonPath('data.legacy_image_url', null);
        $this->getJson('/api/v1/banners')->assertOk()->assertJsonPath('data.0.image_url', null);
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
