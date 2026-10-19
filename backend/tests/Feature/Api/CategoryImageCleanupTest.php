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
use App\Services\CategoryManagementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CategoryImageCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
    }

    #[DataProvider('changes')]
    public function test_unused_category_image_and_thumbnail_are_deleted_after_commit(string $change): void
    {
        $actor = $this->actor();
        $image = $this->image('old');
        $category = Category::factory()->create(['image_id' => $image->id]);
        $new = $change === 'replace' ? $this->image('new') : null;
        if ($change === 'delete') {
            $this->actingAs($actor)->deleteJson("/api/v1/admin/categories/{$category->id}")->assertNoContent();
        } else {
            $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$category->id}", ['image_id' => $new?->id])
                ->assertOk()->assertJsonPath('data.image_id', $new?->id);
        }
        $this->assertDatabaseMissing('media', ['id' => $image->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.deleted', 'entity_id' => $image->id]);
        $this->assertDatabaseCount('storage_cleanup_tasks', 2);
        Queue::assertPushed(DeleteStoredFile::class, 2);
        foreach ([$image->path, $image->thumbnail_path] as $path) {
            Storage::disk('public')->assertExists($path);
            $task = StorageCleanupTask::query()->where('path', $path)->sole();
            $job = new DeleteStoredFile($task->id);
            $job->handle();
            $job->handle();
            Storage::disk('public')->assertMissing($path);
            $this->assertSame('completed', $task->fresh()->status);
        }
        if ($new !== null) {
            $this->assertDatabaseHas('media', ['id' => $new->id]);
            Storage::disk('public')->assertExists($new->path);
        }
    }

    public static function changes(): array
    {
        return [['clear'], ['replace'], ['delete']];
    }

    #[DataProvider('references')]
    public function test_an_image_still_used_elsewhere_is_preserved(string $reference): void
    {
        $actor = $this->actor();
        $image = $this->image('shared');
        $category = Category::factory()->create(['image_id' => $image->id]);
        match ($reference) {
            'category' => Category::factory()->create(['image_id' => $image->id]),
            'brand' => Brand::factory()->create(['logo_id' => $image->id]),
            'banner' => Banner::query()->create(['title' => 'Shared', 'image_media_id' => $image->id]),
            'page' => DB::table('page_media')->insert(['page_id' => Page::query()->where('slug', 'home')->sole()->id, 'media_id' => $image->id]),
            'home' => HomePage::query()->updateOrCreate(['id' => 1], ['content' => [
                'header' => ['logo_media_id' => $image->id], 'about' => ['image_media_id' => null],
                'seo' => ['og_image_media_id' => null], 'categories' => ['items' => []],
            ]]),
            'category-document' => $category->documents()->attach($image->id),
            'brand-document' => Brand::factory()->create()->documents()->attach($image->id),
        };
        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$category->id}", ['image_id' => null])
            ->assertOk()->assertJsonPath('data.image_id', null);
        $this->assertDatabaseHas('media', ['id' => $image->id]);
        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertExists($image->thumbnail_path);
        $this->assertDatabaseCount('storage_cleanup_tasks', 0);
        Queue::assertNothingPushed();
    }

    public static function references(): array
    {
        return array_map(fn (string $reference) => [$reference], ['category', 'brand', 'banner', 'page', 'home', 'category-document', 'brand-document']);
    }

    #[DataProvider('urlReferences')]
    public function test_content_referencing_original_or_thumbnail_by_url_is_preserved(string $reference, bool $thumbnail): void
    {
        $actor = $this->actor();
        $image = $this->image('url-shared');
        $category = Category::factory()->create(['image_id' => $image->id]);
        $url = Storage::disk('public')->url($thumbnail ? $image->thumbnail_path : $image->path);
        $page = Page::query()->where('slug', 'home')->sole();
        match ($reference) {
            'banner' => Banner::query()->create(['title' => 'Shared URL', 'image_url' => $url]),
            'home' => HomePage::query()->updateOrCreate(['id' => 1], ['content' => [
                'header' => ['logo_media_id' => null], 'about' => ['image_media_id' => null, 'image_url' => $url],
                'seo' => ['og_image_media_id' => null], 'categories' => ['items' => []],
            ]]),
            'blocks' => $page->update(['blocks' => [['id' => 'about', 'type' => 'about', 'enabled' => true, 'data' => ['image_url' => $url]]]]),
            'seo' => $page->update(['seo' => ['og_image_url' => 'https://shop.example.test'.$url.'?version=1#photo']]),
            'snapshot' => $page->update(['published_snapshot' => ['blocks' => [['data' => ['image_url' => $url]]]]]),
            'layout' => $page->update(['site_layout' => ['header' => ['logo_url' => $url]]]),
        };
        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$category->id}", ['image_id' => null])->assertOk();
        $this->assertDatabaseHas('media', ['id' => $image->id]);
        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertExists($image->thumbnail_path);
        Queue::assertNothingPushed();
        $this->actingAs($actor)->deleteJson("/api/v1/admin/media/{$image->id}")->assertUnprocessable();
    }

    public static function urlReferences(): array
    {
        $cases = [];
        foreach (['banner', 'home', 'blocks', 'seo', 'snapshot', 'layout'] as $reference) {
            foreach ([false, true] as $thumbnail) {
                $cases[] = [$reference, $thumbnail];
            }
        }

        return $cases;
    }

    public function test_without_media_permission_unlinking_does_not_delete_the_media_file(): void
    {
        $actor = $this->actor();
        Role::query()->where('slug', 'catalog-manager')->sole()->permissions()
            ->detach(Permission::query()->where('code', 'media.manage')->sole()->id);
        $image = $this->image('restricted');
        $category = Category::factory()->create(['image_id' => $image->id]);
        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$category->id}", ['image_id' => null])->assertOk();
        $this->assertDatabaseHas('media', ['id' => $image->id]);
        Storage::disk('public')->assertExists($image->path);
        Queue::assertNothingPushed();
    }

    public function test_unchanged_image_and_failed_update_do_not_schedule_cleanup(): void
    {
        $actor = $this->actor();
        $image = $this->image('unchanged');
        $category = Category::factory()->create(['image_id' => $image->id]);
        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$category->id}", ['image_id' => $image->id, 'name' => 'Changed'])->assertOk();
        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$category->id}", ['image_id' => null, 'slug' => 'Invalid Slug'])->assertUnprocessable();
        $this->assertSame($image->id, $category->fresh()->image_id);
        $this->assertDatabaseHas('media', ['id' => $image->id]);
        $this->assertDatabaseCount('storage_cleanup_tasks', 0);
        Queue::assertNothingPushed();
    }

    public function test_outer_transaction_rollback_restores_image_and_discards_cleanup(): void
    {
        $actor = $this->actor();
        $image = $this->image('rollback');
        $category = Category::factory()->create(['image_id' => $image->id]);
        try {
            DB::transaction(function () use ($actor, $category): void {
                $this->app->make(CategoryManagementService::class)->update($actor, $category, ['image_id' => null]);
                throw new RuntimeException('rollback');
            });
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }
        $this->assertSame($image->id, $category->fresh()->image_id);
        $this->assertDatabaseHas('media', ['id' => $image->id]);
        Storage::disk('public')->assertExists($image->path);
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
        $image = Media::query()->create([
            'kind' => 'image', 'disk' => 'public', 'path' => "media/{$name}.png", 'thumbnail_path' => "media/{$name}-thumb.png",
            'mime_type' => 'image/png', 'size' => 5, 'title' => $name,
        ]);
        Storage::disk('public')->put($image->path, 'image');
        Storage::disk('public')->put($image->thumbnail_path, 'image');

        return $image;
    }
}
