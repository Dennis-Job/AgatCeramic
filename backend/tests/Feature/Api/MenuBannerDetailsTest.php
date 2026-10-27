<?php

namespace Tests\Feature\Api;

use App\Models\HomePage;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Support\PageBlocks;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuBannerDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_details_save_publish_and_read_independently_of_grid_and_global_note(): void
    {
        $this->actingAs($this->manager());
        $content = HomePage::query()->findOrFail(1)->content;
        $menu = $content['materials'];
        $menu['items'][0]['link_url'] = '/catalog?material=tile#products';
        $menu['items'][0]['note'] = 'Первое примечание';
        $menu['items'][1]['link_url'] = 'https://example.com/mosaic';
        $menu['items'][1]['note'] = 'Второе примечание';
        $menu['items'][2]['link_url'] = null;
        $menu['items'][2]['note'] = null;
        $this->patchJson('/api/v1/admin/home-page', ['materials' => $menu])->assertOk()
            ->assertJsonPath('data.materials', $menu)
            ->assertJsonPath('data.categories', $content['categories']);
        $this->getJson('/api/v1/home-page')->assertJsonMissingPath('data.materials.items.0.link_url');
        $this->postJson('/api/v1/admin/home-page/publish')->assertOk();
        $this->getJson('/api/v1/home-page')->assertOk()->assertJsonPath('data.materials', $menu);
        $page = Page::query()->where('slug', 'about')->sole();
        $base = '/api/v1/admin/pages/'.$page->id;
        unset($menu['note']);
        $blocks = [['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => $menu]];
        $this->patchJson($base, ['blocks' => $blocks])->assertOk()->assertJsonPath('data.blocks', $blocks);
        $this->postJson($base.'/publish')->assertOk();
        $this->getJson('/api/v1/pages/about')->assertOk()->assertJsonPath('data.blocks', $blocks);
        $this->getJson($base)->assertOk()->assertJsonPath('data.blocks', $blocks);
    }

    public function test_optional_notes_and_links_accept_absent_null_or_empty_without_global_fallback(): void
    {
        $this->actingAs($this->manager());
        $menu = HomePage::query()->findOrFail(1)->content['materials'];
        foreach ([null, '', 'legacy'] as $legacy) {
            $menu['note'] = $legacy;
            unset($menu['items'][0]['note'], $menu['items'][0]['link_url']);
            $menu['items'][1]['note'] = null;
            $menu['items'][1]['link_url'] = null;
            $menu['items'][2]['note'] = '';
            $menu['items'][2]['link_url'] = '';
            $this->patchJson('/api/v1/admin/home-page', ['materials' => $menu])->assertOk()
                ->assertJsonMissingPath('data.materials.items.0.note')
                ->assertJsonPath('data.materials.items.1.note', null)
                ->assertJsonPath('data.materials.items.2.note', null);
            $this->postJson('/api/v1/admin/home-page/publish')->assertOk();
            $this->getJson('/api/v1/home-page')->assertJsonMissingPath('data.materials.items.0.note')
                ->assertJsonPath('data.materials.items.1.note', null);
        }
        unset($menu['note']);
        $this->patchJson('/api/v1/admin/home-page', ['materials' => $menu])->assertOk();
    }

    public function test_menu_rejects_unsafe_urls_and_long_or_nonstring_notes_in_both_routes(): void
    {
        $this->actingAs($this->manager());
        $page = Page::query()->where('slug', 'about')->sole();
        $menu = HomePage::query()->findOrFail(1)->content['materials'];
        $invalid = [];
        foreach (['javascript:alert(1)', '//evil.example/path', 'https://user:pass@example.com', '/\\evil.example', "/catalog\x01hidden", "https://example.com/a\nb", 'http://example.com', 'catalog/tiles', '/'.str_repeat('a', 2048)] as $url) {
            $invalid[] = ['link_url', $url];
        }
        $invalid[] = ['note', str_repeat('x', 2001)];
        $invalid[] = ['note', ['invalid']];
        foreach ($invalid as [$field, $value]) {
            $item = $menu;
            $item['items'][0][$field] = $value;
            $this->patchJson('/api/v1/admin/home-page', ['materials' => $item])->assertUnprocessable()
                ->assertJsonValidationErrors('materials.items.0.'.$field, 'error.details');
            $this->patchJson('/api/v1/admin/pages/'.$page->id, ['blocks' => [
                ['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => $item],
            ]])->assertUnprocessable()->assertJsonValidationErrors('blocks.0.data.items.0.'.$field, 'error.details');
        }
    }

    public function test_migration_preserves_independent_notes_links_snapshots_metadata_and_is_idempotent(): void
    {
        $page = Page::query()->where('slug', 'home')->sole();
        $content = HomePage::query()->findOrFail(1)->content;
        $menu = $content['materials'];
        $menu['note'] = 'Черновик';
        foreach ($menu['items'] as &$item) {
            unset($item['note']);
        }
        unset($item);
        $menu['items'][1]['note'] = '';
        $menu['items'][2]['note'] = null;
        $menu['items'][] = [...$menu['items'][0], 'id' => 'own', 'note' => 'Своё', 'link_url' => '/own'];
        $blocks = [['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => $menu]];
        $snapshot = $page->published_snapshot;
        $snapshot['blocks'] = $blocks;
        $snapshot['blocks'][0]['data']['note'] = 'Публикация';
        $page->update(['blocks' => $blocks, 'published_snapshot' => $snapshot]);
        $before = $page->fresh();
        $home = HomePage::query()->findOrFail(1);
        $content['materials'] = $menu;
        $content['materials']['note'] = 'Совместимость';
        $home->update(['content' => $content]);
        $homeBefore = $home->fresh();
        $migration = require database_path('migrations/2026_10_27_130000_move_banner_menu_notes_to_items.php');
        $migration->up();
        $page->refresh();
        $expectedBlocks = $blocks;
        $expectedBlocks[0]['data']['items'][0]['note'] = 'Черновик';
        $expectedSnapshot = $snapshot;
        $expectedSnapshot['blocks'][0]['data']['items'][0]['note'] = 'Публикация';
        $expectedContent = $content;
        $expectedContent['materials']['items'][0]['note'] = 'Совместимость';
        self::assertSame($expectedBlocks, $page->blocks);
        self::assertSame($expectedSnapshot, $page->published_snapshot);
        self::assertSame($expectedContent, $home->fresh()->content);
        self::assertEquals($homeBefore->updated_at, $home->fresh()->updated_at);
        self::assertEquals($before->updated_at, $page->updated_at);
        self::assertEquals($before->published_at, $page->published_at);
        self::assertSame($before->is_published, $page->is_published);
        self::assertTrue(PageBlocks::hasUnpublishedChanges($page));
        $migration->up();
        self::assertSame($expectedBlocks, $page->fresh()->blocks);
        self::assertSame($expectedSnapshot, $page->fresh()->published_snapshot);
        self::assertSame($expectedContent, $home->fresh()->content);
    }

    public function test_migration_keeps_clean_pages_clean_and_missing_global_note_and_unpublished_state_intact(): void
    {
        $page = Page::query()->where('slug', 'about')->sole();
        $menu = HomePage::query()->findOrFail(1)->content['materials'];
        foreach ($menu['items'] as &$item) {
            unset($item['note']);
        }
        unset($item);
        $page->blocks = [['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => $menu]];
        $page->published_snapshot = PageBlocks::snapshot($page);
        $page->save();
        $draft = Page::query()->where('slug', 'catalog')->sole();
        unset($menu['note']);
        $draft->update(['blocks' => [['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => $menu]], 'is_published' => false, 'published_snapshot' => null]);
        $draftBefore = $draft->fresh();
        $migration = require database_path('migrations/2026_10_27_130000_move_banner_menu_notes_to_items.php');
        $migration->up();
        $page->refresh();
        self::assertSame($page->blocks[0]['data']['note'], $page->blocks[0]['data']['items'][0]['note']);
        self::assertFalse(PageBlocks::hasUnpublishedChanges($page));
        self::assertSame($draftBefore->blocks, $draft->fresh()->blocks);
        self::assertNull($draft->fresh()->published_snapshot);
        self::assertFalse($draft->fresh()->is_published);
    }

    public function test_migration_copies_explicit_empty_or_null_legacy_notes_without_inventing_items(): void
    {
        $home = HomePage::query()->findOrFail(1);
        $content = $home->content;
        $migration = require database_path('migrations/2026_10_27_130000_move_banner_menu_notes_to_items.php');
        foreach (['', null] as $note) {
            $content['materials']['note'] = $note;
            unset($content['materials']['items'][0]['note']);
            $home->update(['content' => $content]);
            $migration->up();
            self::assertArrayHasKey('note', $home->fresh()->content['materials']['items'][0]);
            self::assertSame($note, $home->fresh()->content['materials']['items'][0]['note']);
        }
        unset($content['materials']['items']);
        $home->update(['content' => $content]);
        $migration->up();
        self::assertSame($content, $home->fresh()->content);
    }

    public function test_menu_details_require_content_management_permission(): void
    {
        $this->actingAs($this->manager());
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::query()->where('slug', 'analyst')->sole());
        $menu = HomePage::query()->findOrFail(1)->content['materials'];
        $menu['items'][0]['link_url'] = '/catalog';
        $menu['items'][0]['note'] = 'Примечание';
        $page = Page::query()->where('slug', 'about')->sole();
        $this->actingAs($actor)->patchJson('/api/v1/admin/home-page', ['materials' => $menu])->assertForbidden();
        $this->patchJson('/api/v1/admin/pages/'.$page->id, ['blocks' => [
            ['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => $menu],
        ]])->assertForbidden();
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
