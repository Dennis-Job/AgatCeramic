<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\MediaManagementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PageBlockContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_do_not_change_public_snapshot_until_explicit_publication(): void
    {
        $actor = $this->manager();
        $page = Page::query()->where('slug', 'about')->sole();
        $original = $this->getJson('/api/v1/pages/about')->assertOk()->json('data');
        $blocks = [
            ['id' => 'second', 'type' => 'text', 'enabled' => false, 'data' => ['title' => 'Второй', 'body' => 'Два']],
            ['id' => 'first', 'type' => 'text', 'enabled' => true, 'data' => ['title' => 'Первый', 'body' => '<script>unsafe()</script>Текст']],
        ];
        $this->actingAs($actor)->patchJson('/api/v1/admin/pages/'.$page->id, ['title' => 'Черновик', 'blocks' => $blocks])
            ->assertOk()->assertJsonPath('data.has_unpublished_changes', true)->assertJsonPath('data.blocks.0.id', 'second');
        $this->getJson('/api/v1/pages/about')->assertJsonPath('data.title', $original['title'])->assertJsonPath('data.blocks', $original['blocks'])
            ->assertJsonMissingPath('data.has_unpublished_changes')->assertJsonMissingPath('data.published_snapshot');
        $this->actingAs($actor)->postJson('/api/v1/admin/pages/'.$page->id.'/publish')->assertOk()->assertJsonPath('data.has_unpublished_changes', false);
        $this->getJson('/api/v1/pages/about')->assertJsonPath('data.title', 'Черновик')->assertJsonPath('data.blocks', [$blocks[1]]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.published', 'entity_id' => $page->id]);
        $this->actingAs($actor)->patchJson('/api/v1/admin/pages/'.$page->id, ['is_published' => false])->assertOk();
        $this->getJson('/api/v1/pages/about')->assertNotFound();
        $this->actingAs($actor)->patchJson('/api/v1/admin/pages/'.$page->id, ['is_published' => true])->assertUnprocessable();
    }

    public function test_blocks_reject_unknown_types_unknown_fields_duplicates_unsafe_links_and_unavailable_media(): void
    {
        $actor = $this->manager();
        $page = Page::query()->where('slug', 'home')->sole();
        $base = '/api/v1/admin/pages/'.$page->id;
        $this->actingAs($actor)->patchJson($base, ['blocks' => [['id' => 'bad', 'type' => 'html', 'enabled' => true, 'data' => []]]])->assertUnprocessable();
        $this->actingAs($actor)->patchJson($base, ['unknown' => true])->assertUnprocessable();
        $block = ['id' => 'stores', 'type' => 'stores', 'enabled' => true, 'data' => ['title' => 'Магазины', 'html' => 'bad']];
        $this->actingAs($actor)->patchJson($base, ['blocks' => [$block]])->assertUnprocessable();
        unset($block['data']['html']);
        $duplicate = $block;
        $duplicate['id'] = 'other-stores';
        $this->actingAs($actor)->patchJson($base, ['blocks' => [$block, $duplicate]])->assertUnprocessable();
        $promo = HomePage::query()->findOrFail(1)->content['promo'];
        $promo['link_url'] = 'javascript:alert(1)';
        $this->actingAs($actor)->patchJson($base, ['blocks' => [['id' => 'promo', 'type' => 'promo', 'enabled' => true, 'data' => $promo]]])->assertUnprocessable();
        $about = HomePage::query()->findOrFail(1)->content['about'];
        $about['image_media_id'] = 999999;
        $this->actingAs($actor)->patchJson($base, ['blocks' => [['id' => 'about', 'type' => 'about', 'enabled' => true, 'data' => $about]]])->assertUnprocessable();
        $analyst = $this->manager('analyst');
        $this->actingAs($analyst)->postJson($base.'/publish')->assertForbidden();
        $this->actingAs($actor)->deleteJson($base)->assertUnprocessable();
        $this->actingAs($actor)->patchJson($base, ['slug' => 'changed-home'])->assertUnprocessable();
    }

    public function test_both_draft_and_published_images_are_protected_from_deletion(): void
    {
        $actor = $this->manager();
        $media = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'media/about.webp', 'mime_type' => 'image/webp', 'size' => 12, 'title' => 'О нас']);
        $page = Page::query()->where('slug', 'about')->sole();
        $about = HomePage::query()->findOrFail(1)->content['about'];
        $about['image_media_id'] = $media->id;
        $this->actingAs($actor)->patchJson('/api/v1/admin/pages/'.$page->id, ['blocks' => [['id' => 'about', 'type' => 'about', 'enabled' => true, 'data' => $about]]])->assertOk();
        $this->assertMediaProtected($actor, $media);
        $this->actingAs($actor)->postJson('/api/v1/admin/pages/'.$page->id.'/publish')->assertOk();
        $this->actingAs($actor)->patchJson('/api/v1/admin/pages/'.$page->id, ['blocks' => []])->assertOk();
        $this->assertMediaProtected($actor, $media);
        $this->actingAs($actor)->postJson('/api/v1/admin/pages/'.$page->id.'/publish')->assertOk();
        app(MediaManagementService::class)->delete($actor, $media);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_catalog_has_only_active_domain_data_and_public_projection(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $hidden = Category::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);
        Product::factory()->create(['category_id' => $hidden->id, 'is_active' => true]);
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id)
            ->assertJsonCount(1, 'categories')->assertJsonMissingPath('data.0.sku')->assertJsonMissingPath('data.0.stock_quantity')
            ->assertJsonPath('meta.per_page', 24);
    }

    public function test_draft_slug_does_not_move_the_published_url_or_release_it_for_other_pages(): void
    {
        $actor = $this->manager();
        $id = $this->actingAs($actor)->postJson('/api/v1/admin/pages', ['title' => 'Доставка', 'slug' => 'delivery', 'body' => 'Условия'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish')->assertOk();
        $this->patchJson('/api/v1/admin/pages/'.$id, ['slug' => 'shipping'])->assertOk();
        $this->getJson('/api/v1/pages/delivery')->assertOk()->assertJsonPath('data.slug', 'delivery');
        $this->getJson('/api/v1/pages/shipping')->assertNotFound();
        $this->postJson('/api/v1/admin/pages', ['title' => 'Другой текст', 'slug' => 'delivery', 'body' => 'Текст'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish')->assertOk();
        $this->getJson('/api/v1/pages/delivery')->assertNotFound();
        $this->getJson('/api/v1/pages/shipping')->assertOk();
    }

    public function test_layout_draft_is_isolated_and_published_logo_media_remains_protected(): void
    {
        $actor = $this->manager();
        $first = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'media/first.webp', 'mime_type' => 'image/webp', 'size' => 12, 'title' => 'Первый логотип']);
        $second = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'media/second.webp', 'mime_type' => 'image/webp', 'size' => 12, 'title' => 'Второй логотип']);
        $header = $this->actingAs($actor)->getJson('/api/v1/admin/home-page')->json('data.header');
        unset($header['logo_url']);
        $header['logo_media_id'] = $first->id;
        $this->patchJson('/api/v1/admin/home-page', ['header' => $header])->assertOk();
        $this->postJson('/api/v1/admin/home-page/publish')->assertOk();
        $header['logo_media_id'] = $second->id;
        $footer = $this->getJson('/api/v1/admin/home-page')->json('data.footer');
        $oldTagline = $footer['tagline'];
        $footer['tagline'] = 'Новый черновик подвала';
        $this->patchJson('/api/v1/admin/home-page', ['header' => $header, 'footer' => $footer])->assertOk()->assertJsonPath('data.has_unpublished_changes', true);
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.footer.tagline', $oldTagline)->assertJsonPath('data.header.logo_media_id', $first->id);
        $this->assertMediaProtected($actor, $first);
        $this->postJson('/api/v1/admin/home-page/publish')->assertOk()->assertJsonPath('data.has_unpublished_changes', false);
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.footer.tagline', 'Новый черновик подвала')->assertJsonPath('data.header.logo_media_id', $second->id);
        app(MediaManagementService::class)->delete($actor, $first);
        $this->assertDatabaseMissing('media', ['id' => $first->id]);
    }

    public function test_additive_migration_preserves_existing_page_and_home_data(): void
    {
        $migration = require database_path('migrations/2026_09_30_120000_add_page_content_snapshots.php');
        Schema::drop('page_media');
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropUnique(['published_slug']);
            $table->dropColumn(['blocks', 'seo', 'published_snapshot', 'published_at', 'published_slug', 'site_layout']);
        });
        DB::table('pages')->delete();
        DB::table('pages')->insert(['title' => 'Существующая история', 'slug' => 'about', 'body' => 'Старый текст', 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pages')->insert(['title' => 'Старый черновик главной', 'slug' => 'home', 'body' => 'Секретный черновик', 'is_published' => false, 'created_at' => now(), 'updated_at' => now()]);
        $home = HomePage::query()->findOrFail(1);
        $content = $home->content;
        $content['promo']['title'] = 'Сохранённая акция';
        $home->setAttribute('content', $content)->save();
        $migration->up();
        $this->getJson('/api/v1/pages/about')->assertOk()->assertJsonPath('data.title', 'Существующая история')->assertJsonPath('data.body', 'Старый текст');
        $this->getJson('/api/v1/home-page')->assertOk()->assertJsonPath('data.promo.title', 'Сохранённая акция');
        $this->getJson('/api/v1/pages/home')->assertOk()->assertJsonPath('data.body', '')->assertJsonCount(7, 'data.blocks');
        $homePage = Page::query()->where('slug', 'home')->sole();
        self::assertSame('Секретный черновик', $homePage->body);
        self::assertSame('Секретный черновик', $homePage->blocks[7]['data']['body']);
        $this->assertDatabaseCount('pages', 4);
    }

    private function assertMediaProtected(User $actor, Media $media): void
    {
        try {
            app(MediaManagementService::class)->delete($actor, $media);
            self::fail('Page image must be protected from deletion.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('media', $exception->errors());
        }
    }

    private function manager(string $slug = 'content-manager'): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
