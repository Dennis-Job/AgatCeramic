<?php

namespace Tests\Feature\Api;

use App\Jobs\DeleteStoredFile;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StorageCleanupTask;
use App\Models\User;
use App\Services\BrandManagementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BrandLogoCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
    }

    #[DataProvider('changes')]
    public function test_unused_logo_and_thumbnail_are_cleaned_after_successful_brand_change(string $change): void
    {
        $actor = $this->actor();
        $logo = $this->image('old');
        $brand = Brand::factory()->create(['logo_id' => $logo->id]);
        $replacement = $change === 'replace' ? $this->image('new') : null;
        if ($change === 'delete') {
            $this->actingAs($actor)->deleteJson("/api/v1/admin/brands/{$brand->id}")->assertNoContent();
        } else {
            $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$brand->id}", ['logo_id' => $replacement?->id])
                ->assertOk()->assertJsonPath('data.logo_id', $replacement?->id);
        }

        $this->assertDatabaseMissing('media', ['id' => $logo->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.deleted', 'entity_id' => $logo->id]);
        $this->assertDatabaseCount('storage_cleanup_tasks', 2);
        Queue::assertPushed(DeleteStoredFile::class, 2);
        foreach ([$logo->path, $logo->thumbnail_path] as $path) {
            Storage::disk('public')->assertExists($path);
            $task = StorageCleanupTask::query()->where('path', $path)->sole();
            $job = new DeleteStoredFile($task->id);
            $job->handle();
            $job->handle();
            Storage::disk('public')->assertMissing($path);
            $this->assertSame('completed', $task->fresh()->status);
        }
        if ($replacement !== null) {
            $this->assertDatabaseHas('media', ['id' => $replacement->id]);
            Storage::disk('public')->assertExists($replacement->path);
            Storage::disk('public')->assertExists($replacement->thumbnail_path);
        }
    }

    /** @return list<array{string}> */
    public static function changes(): array
    {
        return [['clear'], ['replace'], ['delete']];
    }

    #[DataProvider('references')]
    public function test_a_logo_referenced_elsewhere_is_preserved(string $reference): void
    {
        $actor = $this->actor();
        $logo = $this->image('shared');
        $brand = Brand::factory()->create(['logo_id' => $logo->id]);
        match ($reference) {
            'brand' => Brand::factory()->create(['logo_id' => $logo->id]),
            'category' => Category::factory()->create(['image_id' => $logo->id]),
            'banner' => Banner::query()->create(['title' => 'Shared', 'image_media_id' => $logo->id]),
            'page' => DB::table('page_media')->insert(['page_id' => Page::query()->where('slug', 'home')->sole()->id, 'media_id' => $logo->id]),
            'home' => HomePage::query()->updateOrCreate(['id' => 1], ['content' => [
                'header' => ['logo_media_id' => $logo->id], 'about' => ['image_media_id' => null],
                'seo' => ['og_image_media_id' => null], 'categories' => ['items' => []],
            ]]),
            'brand-document' => $brand->documents()->attach($logo->id),
            'category-document' => Category::factory()->create()->documents()->attach($logo->id),
        };
        $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$brand->id}", ['logo_id' => null])->assertOk();
        $this->assertPreserved($logo);
    }

    /** @return list<array{string}> */
    public static function references(): array
    {
        return array_map(fn (string $reference) => [$reference], ['brand', 'category', 'banner', 'page', 'home', 'brand-document', 'category-document']);
    }

    #[DataProvider('urlReferences')]
    public function test_a_logo_or_thumbnail_used_by_content_url_is_preserved(string $reference, bool $thumbnail): void
    {
        $actor = $this->actor();
        $logo = $this->image('url-shared');
        $brand = Brand::factory()->create(['logo_id' => $logo->id]);
        $url = Storage::disk('public')->url($thumbnail ? $logo->thumbnail_path : $logo->path);
        if ($reference === 'banner') {
            Banner::query()->create(['title' => 'Shared URL', 'image_url' => $url]);
        } else {
            Page::query()->where('slug', 'home')->sole()->update(['published_snapshot' => [
                'blocks' => [['data' => ['image_url' => 'https://shop.example.test'.$url.'?version=1#logo']]],
            ]]);
        }
        $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$brand->id}", ['logo_id' => null])->assertOk();
        $this->assertPreserved($logo);
    }

    /** @return list<array{string, bool}> */
    public static function urlReferences(): array
    {
        return [['banner', false], ['banner', true], ['snapshot', false], ['snapshot', true]];
    }

    #[DataProvider('changes')]
    public function test_without_media_permission_brand_changes_do_not_delete_the_logo(string $change): void
    {
        $actor = $this->actor();
        Role::query()->where('slug', 'catalog-manager')->sole()->permissions()
            ->detach(Permission::query()->where('code', 'media.manage')->sole()->id);
        $logo = $this->image('restricted');
        $brand = Brand::factory()->create(['logo_id' => $logo->id]);
        if ($change === 'delete') {
            $this->actingAs($actor)->deleteJson("/api/v1/admin/brands/{$brand->id}")->assertNoContent();
        } else {
            $replacement = $change === 'replace' ? $this->image('new') : null;
            $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$brand->id}", ['logo_id' => $replacement?->id])->assertOk();
        }
        $this->assertPreserved($logo);
    }

    public function test_unchanged_logo_and_failed_update_do_not_schedule_cleanup(): void
    {
        $actor = $this->actor();
        $logo = $this->image('unchanged');
        $brand = Brand::factory()->create(['logo_id' => $logo->id]);
        $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$brand->id}", ['name' => 'Renamed'])->assertOk();
        $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$brand->id}", ['logo_id' => $logo->id])->assertOk();
        $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$brand->id}", ['logo_id' => null, 'slug' => 'Invalid Slug'])->assertUnprocessable();
        $this->assertSame($logo->id, Brand::query()->findOrFail($brand->id)->logo_id);
        $this->assertPreserved($logo);
    }

    #[DataProvider('transactionChanges')]
    public function test_rollback_restores_the_brand_logo_and_discards_cleanup(string $change): void
    {
        $actor = $this->actor();
        $logo = $this->image('rollback');
        $brand = Brand::factory()->create(['logo_id' => $logo->id]);
        try {
            DB::transaction(function () use ($actor, $brand, $change): void {
                $service = $this->app->make(BrandManagementService::class);
                if ($change === 'delete') {
                    $service->delete($actor, $brand);
                } else {
                    $service->update($actor, $brand, ['logo_id' => null]);
                }
                throw new RuntimeException('rollback');
            });
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }
        $this->assertSame($logo->id, Brand::query()->findOrFail($brand->id)->logo_id);
        $this->assertPreserved($logo);
    }

    #[DataProvider('transactionChanges')]
    public function test_cleanup_uses_the_current_logo_when_the_brand_instance_is_stale(string $change): void
    {
        $actor = $this->actor();
        $oldLogo = $this->image('old');
        $currentLogo = $this->image('current');
        $brand = Brand::factory()->create(['logo_id' => $oldLogo->id]);
        Brand::query()->whereKey($brand->id)->update(['logo_id' => $currentLogo->id]);
        $service = $this->app->make(BrandManagementService::class);
        if ($change === 'delete') {
            $service->delete($actor, $brand);
        } else {
            $service->update($actor, $brand, ['logo_id' => null]);
        }
        $this->assertDatabaseMissing('media', ['id' => $currentLogo->id]);
        $this->assertDatabaseHas('media', ['id' => $oldLogo->id]);
        $this->assertDatabaseCount('storage_cleanup_tasks', 2);
    }

    /** @return list<array{string}> */
    public static function transactionChanges(): array
    {
        return [['clear'], ['delete']];
    }

    private function assertPreserved(Media $logo): void
    {
        $this->assertDatabaseHas('media', ['id' => $logo->id]);
        Storage::disk('public')->assertExists($logo->path);
        Storage::disk('public')->assertExists($logo->thumbnail_path);
        $this->assertDatabaseCount('storage_cleanup_tasks', 0);
        Queue::assertNothingPushed();
    }

    private function actor(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());

        return $user;
    }

    private function image(string $name): Media
    {
        $logo = Media::query()->create([
            'kind' => 'image', 'disk' => 'public', 'path' => "media/{$name}.png", 'thumbnail_path' => "media/{$name}-thumb.png",
            'mime_type' => 'image/png', 'size' => 5, 'title' => $name,
        ]);
        Storage::disk('public')->put($logo->path, 'image');
        Storage::disk('public')->put($logo->thumbnail_path, 'image');

        return $logo;
    }
}
