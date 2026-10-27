<?php

namespace Tests\Feature\Api;

use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Services\MediaManagementService;
use App\Support\PageBlocks;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BannerBlockIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_and_menu_save_and_publish_independent_items_with_overlapping_ids(): void
    {
        $actor = $this->manager();
        $page = Page::query()->where('slug', 'home')->sole();
        $blocks = $this->bannerBlocks();
        $blocks[0]['data']['items'][0]['name'] = 'Сетка';
        $blocks[1]['data']['items'][0]['name'] = 'Меню';
        $base = '/api/v1/admin/pages/'.$page->id;
        $this->actingAs($actor)->patchJson($base, ['blocks' => $blocks])->assertOk()
            ->assertJsonPath('data.blocks.0.data.items.0.name', 'Сетка')
            ->assertJsonPath('data.blocks.1.data.items.0.name', 'Меню');
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.materials.items.0.name', 'Керамогранит');
        $this->postJson($base.'/publish')->assertOk()->assertJsonPath('data.has_unpublished_changes', false);
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.categories.items.0.name', 'Сетка')
            ->assertJsonPath('data.materials.items.0.name', 'Меню');
        $blocks[0]['data']['items'][0]['name'] = 'Другой черновик сетки';
        $this->patchJson($base, ['blocks' => $blocks])->assertOk();
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.categories.items.0.name', 'Сетка')
            ->assertJsonPath('data.materials.items.0.name', 'Меню');
        $legacy = $this->getJson('/api/v1/admin/home-page')->json('data.materials');
        $legacy['items'][0]['name'] = 'Новый черновик меню';
        $this->patchJson('/api/v1/admin/home-page', ['materials' => $legacy])->assertOk()
            ->assertJsonPath('data.materials.items.0.name', 'Новый черновик меню')
            ->assertJsonPath('data.categories.items.0.name', 'Другой черновик сетки');
    }

    public function test_standalone_menu_accepts_empty_and_null_images_but_validates_its_own_items(): void
    {
        $this->actingAs($this->manager());
        $page = Page::query()->where('slug', 'about')->sole();
        $base = '/api/v1/admin/pages/'.$page->id;
        $menu = $this->bannerBlocks()[1];
        $menu['data']['items'][0]['image_url'] = null;
        $menu['data']['items'][0]['image_media_id'] = null;
        $this->patchJson($base, ['blocks' => [$menu]])->assertOk();
        $invalid = $menu;
        $invalid['data']['items'][1]['id'] = $invalid['data']['items'][0]['id'];
        $this->patchJson($base, ['blocks' => [$invalid]])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['blocks.0.data.items.0.id']]]);
        $invalid = $menu;
        $invalid['data']['items'][0]['image_url'] = 'javascript:alert(1)';
        $this->patchJson($base, ['blocks' => [$invalid]])->assertUnprocessable();
        $invalid = $menu;
        $invalid['data']['items'][0]['image_media_id'] = 999999;
        $this->patchJson($base, ['blocks' => [$invalid]])->assertUnprocessable();
        $invalid['data']['items'][0]['image_media_id'] = $this->media('private', 'local')->id;
        $this->patchJson($base, ['blocks' => [$invalid]])->assertUnprocessable();
        $invalid = $menu;
        unset($invalid['data']['items']);
        $this->patchJson($base, ['blocks' => [$invalid]])->assertUnprocessable();
        $invalid = $menu;
        $invalid['data']['items'] = array_map(static fn (int $id): array => [...$menu['data']['items'][0], 'id' => 'banner-'.$id], range(1, 13));
        $this->patchJson($base, ['blocks' => [$invalid]])->assertUnprocessable();
        $menu['data']['items'] = [];
        $this->patchJson($base, ['blocks' => [$menu]])->assertOk();
        $this->postJson($base.'/publish')->assertOk();
        $this->getJson('/api/v1/pages/about')->assertJsonPath('data.blocks.0.data.items', []);
        $this->patchJson('/api/v1/admin/home-page', ['materials' => $menu['data']])->assertOk();
    }

    public function test_menu_only_media_resolves_and_remains_protected_in_draft_and_snapshot(): void
    {
        $actor = $this->manager();
        $media = $this->media('menu');
        $menu = $this->bannerBlocks()[1];
        $menu['data']['items'][0]['image_media_id'] = $media->id;
        $menu['data']['items'][0]['image_url'] = null;
        $page = Page::query()->where('slug', 'about')->sole();
        $base = '/api/v1/admin/pages/'.$page->id;
        $this->actingAs($actor)->patchJson($base, ['blocks' => [$menu]])->assertOk();
        $this->assertProtected($actor, $media);
        $this->postJson($base.'/publish')->assertOk();
        $this->getJson('/api/v1/pages/about')->assertJsonPath('data.blocks.0.data.items.0.image_url', Storage::disk('public')->url('media/menu.webp'));
        $this->patchJson($base, ['blocks' => []])->assertOk();
        $this->assertProtected($actor, $media);
        $this->postJson($base.'/publish')->assertOk();
        app(MediaManagementService::class)->delete($actor, $media);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_migration_copies_each_draft_and_snapshot_once_without_republishing(): void
    {
        $page = Page::query()->where('slug', 'home')->sole();
        $blocks = $this->bannerBlocks();
        unset($blocks[1]['data']['items']);
        $snapshot = $page->published_snapshot;
        $snapshot['blocks'] = $blocks;
        $snapshot['blocks'][0]['data']['items'][0]['name'] = 'Опубликовано';
        $blocks[0]['data']['items'][0]['name'] = 'Черновик';
        $page->update(['blocks' => $blocks, 'published_snapshot' => $snapshot]);
        $before = $page->fresh();
        $home = HomePage::query()->findOrFail(1);
        $content = $home->content;
        unset($content['materials']['items']);
        $home->update(['content' => $content]);
        $migration = require database_path('migrations/2026_10_27_120000_separate_banner_menu_items.php');
        $migration->up();
        $page->refresh();
        self::assertSame('Черновик', $page->blocks[1]['data']['items'][0]['name']);
        self::assertSame('Опубликовано', $page->published_snapshot['blocks'][1]['data']['items'][0]['name']);
        self::assertEquals($before->published_at, $page->published_at);
        self::assertEquals($before->updated_at, $page->updated_at);
        self::assertSame($before->is_published, $page->is_published);
        self::assertTrue(PageBlocks::hasUnpublishedChanges($page));
        self::assertSame($content['categories']['items'], $home->fresh()->content['materials']['items']);
        $blocks = $page->blocks;
        $blocks[1]['data']['items'] = [];
        $page->update(['blocks' => $blocks]);
        $savedSnapshot = $page->published_snapshot;
        $migration->up();
        $page->refresh();
        self::assertSame([], $page->blocks[1]['data']['items']);
        self::assertSame($savedSnapshot, $page->published_snapshot);
    }

    public function test_migration_keeps_clean_pages_clean_and_gives_standalone_menus_empty_lists(): void
    {
        $page = Page::query()->where('slug', 'about')->sole();
        $menu = $this->bannerBlocks()[1];
        unset($menu['data']['items']);
        $page->blocks = [$menu];
        $page->published_snapshot = PageBlocks::snapshot($page);
        $page->save();
        $migration = require database_path('migrations/2026_10_27_120000_separate_banner_menu_items.php');
        $migration->up();
        $page->refresh();
        self::assertSame([], $page->blocks[0]['data']['items']);
        self::assertSame([], $page->published_snapshot['blocks'][0]['data']['items']);
        self::assertFalse(PageBlocks::hasUnpublishedChanges($page));
    }

    public function test_migration_preserves_existing_independent_menu_lists_and_unpublished_pages(): void
    {
        $page = Page::query()->where('slug', 'about')->sole();
        $blocks = $this->bannerBlocks();
        $blocks[1]['data']['items'][0]['name'] = 'Собственное меню';
        $page->update(['blocks' => $blocks, 'is_published' => false, 'published_snapshot' => null]);
        $home = HomePage::query()->findOrFail(1);
        $content = $home->content;
        $content['materials']['items'] = [];
        $home->update(['content' => $content]);
        $before = $page->fresh();
        $migration = require database_path('migrations/2026_10_27_120000_separate_banner_menu_items.php');
        $migration->up();
        $migration->up();
        $page->refresh();
        self::assertSame($before->blocks, $page->blocks);
        self::assertFalse($page->is_published);
        self::assertNull($page->published_snapshot);
        self::assertEquals($before->updated_at, $page->updated_at);
        self::assertSame([], $home->fresh()->content['materials']['items']);
    }

    /** @return list<array{id: string, type: string, enabled: bool, data: array<string, mixed>}> */
    private function bannerBlocks(): array
    {
        $content = HomePage::query()->findOrFail(1)->content;

        return [
            ['id' => 'grid', 'type' => 'categories', 'enabled' => true, 'data' => $content['categories']],
            ['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => [...$content['materials'], 'items' => $content['categories']['items']]],
        ];
    }

    private function media(string $name, string $disk = 'public'): Media
    {
        return Media::query()->create(['kind' => 'image', 'disk' => $disk, 'path' => 'media/'.$name.'.webp', 'mime_type' => 'image/webp', 'size' => 12, 'title' => $name]);
    }

    private function assertProtected(User $actor, Media $media): void
    {
        try {
            app(MediaManagementService::class)->delete($actor, $media);
            self::fail('Menu media must be protected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('media', $exception->errors());
        }
    }

    private function manager(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'content-manager')->sole());

        return $user;
    }
}
