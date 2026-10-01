<?php

namespace Tests\Feature\Api;

use App\Enums\AdminUserStatus;
use App\Models\Banner;
use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\Slider;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ContentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_requires_a_current_active_content_manager_and_protects_error_responses(): void
    {
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/home')->assertUnauthorized());
        $this->assertPrivate($this->actingAs($this->manager('analyst'))->getJson('/api/v1/admin/content-preview/home')->assertForbidden());
        $actor = $this->manager();
        $this->assertPrivate($this->actingAs($actor)->getJson('/api/v1/admin/content-preview/home')->assertOk());
        $actor->roles()->detach();
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/home')->assertForbidden());
        $actor->roles()->attach(Role::query()->where('slug', 'content-manager')->sole());
        $actor->update(['status' => AdminUserStatus::Blocked]);
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/home')->assertForbidden());
    }

    public function test_real_nuxt_origin_session_can_preview_and_logout_revokes_access(): void
    {
        // Use the migrated in-memory test database so each fresh session store
        // must recover authentication from the browser's encrypted cookie.
        config(['session.driver' => 'database', 'sanctum.stateful' => ['localhost:5173', 'localhost:3000']]);
        $this->freshSessionRequest();
        $actor = $this->manager();
        $login = $this->withHeader('Origin', 'http://localhost:5173')->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/login', ['email' => $actor->email, 'password' => 'password'])->assertNoContent();
        $cookieName = config('session.cookie');
        $cookie = $login->getCookie($cookieName, false);
        self::assertNotNull($cookie);
        $this->withCredentials()->withUnencryptedCookie($cookieName, $cookie->getValue());

        $this->freshSessionRequest();
        $this->withHeader('Origin', 'http://localhost:3000');
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/home')->assertOk());

        // Origin is absent for some same-origin GETs; Sanctum accepts the
        // origin-only referrer explicitly supplied by the preview fetch.
        $this->freshSessionRequest();
        $this->withoutHeader('Origin')->withHeader('Referer', 'http://localhost:3000/');
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/home')->assertOk());

        // An encrypted cookie alone must not authorize an unidentified SPA.
        $this->freshSessionRequest();
        $this->withoutHeader('Referer');
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/home')->assertUnauthorized());

        $this->freshSessionRequest();
        $this->withHeader('Origin', 'http://localhost:5173');
        $this->postJson('/api/v1/admin/auth/logout')->assertNoContent();
        // Replay the original cookie after logout, with fresh guards/storage.
        $this->freshSessionRequest();
        $this->withHeader('Origin', 'http://localhost:3000');
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/home')->assertUnauthorized());
    }

    private function freshSessionRequest(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    public function test_preview_reads_saved_draft_independently_of_publication_and_withdrawal(): void
    {
        $actor = $this->manager();
        $page = Page::query()->where('slug', 'about')->sole();
        $published = $this->getJson('/api/v1/pages/about')->assertOk()->json('data');
        $blocks = [
            ['id' => 'hidden', 'type' => 'text', 'enabled' => false, 'data' => ['title' => 'Hidden', 'body' => 'Hidden draft']],
            ['id' => 'visible', 'type' => 'text', 'enabled' => true, 'data' => ['title' => 'Saved', 'body' => 'Saved draft']],
        ];
        $this->actingAs($actor)->patchJson('/api/v1/admin/pages/'.$page->id, ['title' => 'Saved draft title', 'blocks' => $blocks])->assertOk();
        $before = $page->refresh()->getAttributes();
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/about')->assertOk()
            ->assertJsonPath('data.page.title', 'Saved draft title')->assertJsonPath('data.page.blocks', [$blocks[1]])
            ->assertJsonMissingPath('data.page.published_snapshot')->assertJsonMissingPath('data.page.is_published')
            ->assertJsonMissingPath('data.appearance.has_unpublished_changes'));
        self::assertSame($before, $page->refresh()->getAttributes());
        $this->getJson('/api/v1/pages/about')->assertJsonPath('data.title', $published['title'])->assertJsonPath('data.blocks', $published['blocks']);
        $this->postJson('/api/v1/admin/pages/'.$page->id.'/publish')->assertOk();
        $this->getJson('/api/v1/pages/about')->assertJsonPath('data.title', 'Saved draft title')->assertJsonPath('data.blocks', [$blocks[1]]);
        $this->patchJson('/api/v1/admin/pages/'.$page->id, ['is_published' => false])->assertOk();
        $this->getJson('/api/v1/pages/about')->assertNotFound();
        $this->getJson('/api/v1/admin/content-preview/about')->assertOk()->assertJsonPath('data.page.title', 'Saved draft title');
        $this->assertPrivate($this->getJson('/api/v1/admin/content-preview/missing')->assertNotFound());
    }

    public function test_preview_uses_current_draft_slug_and_never_exposes_it_through_public_url(): void
    {
        $actor = $this->manager();
        $id = $this->actingAs($actor)->postJson('/api/v1/admin/pages', ['title' => 'Draft only', 'slug' => 'draft-only', 'body' => 'Private saved body'])
            ->assertCreated()->json('data.id');
        $this->getJson('/api/v1/admin/content-preview/draft-only')->assertOk()->assertJsonPath('data.page.body', 'Private saved body');
        $this->getJson('/api/v1/pages/draft-only')->assertNotFound();
        $this->getJson('/api/v1/pages')->assertJsonMissing(['slug' => 'draft-only']);
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish')->assertOk();
        $this->patchJson('/api/v1/admin/pages/'.$id, ['slug' => 'draft-renamed'])->assertOk();
        $this->getJson('/api/v1/admin/content-preview/draft-renamed')->assertOk()->assertJsonPath('data.page.slug', 'draft-renamed');
        $this->getJson('/api/v1/admin/content-preview/draft-only')->assertNotFound();
        $this->getJson('/api/v1/pages/draft-only')->assertOk();
        $this->getJson('/api/v1/pages/draft-renamed')->assertNotFound();
    }

    public function test_home_legacy_edits_and_shared_appearance_use_saved_drafts_without_public_leakage(): void
    {
        $actor = $this->manager();
        $publicHome = $this->getJson('/api/v1/home-page')->assertOk()->json('data');
        $publicAppearance = $this->getJson('/api/v1/site-appearance')->assertOk()->json('data');
        $promo = HomePage::query()->findOrFail(1)->content['promo'];
        $promo['title'] = 'Saved legacy home draft';
        $this->actingAs($actor)->patchJson('/api/v1/admin/home-page', ['promo' => $promo])->assertOk();
        $layout = Page::query()->where('slug', 'home')->sole()->site_layout;
        $layout['header']['topbar_left'] = 'Draft chrome';
        $layout['footer']['tagline'] = 'Draft footer';
        $this->patchJson('/api/v1/admin/site-appearance', $layout)->assertOk();
        $home = $this->getJson('/api/v1/admin/content-preview/home')->assertOk()->assertJsonPath('data.appearance.header.topbar_left', 'Draft chrome')
            ->assertJsonPath('data.appearance.footer.tagline', 'Draft footer')->json('data.page.blocks');
        self::assertSame('Saved legacy home draft', collect($home)->firstWhere('type', 'promo')['data']['title']);
        $this->getJson('/api/v1/admin/content-preview/contacts')->assertOk()->assertJsonPath('data.appearance.header.topbar_left', 'Draft chrome');
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.promo', $publicHome['promo']);
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data', $publicAppearance);
        $homePage = Page::query()->where('slug', 'home')->sole();
        $this->postJson('/api/v1/admin/pages/'.$homePage->id.'/publish')->assertOk();
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.promo.title', 'Saved legacy home draft');
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data', $publicAppearance);
        $this->patchJson('/api/v1/admin/pages/'.$homePage->id, ['is_published' => false])->assertOk();
        $this->getJson('/api/v1/home-page')->assertNotFound();
        $this->getJson('/api/v1/admin/content-preview/home')->assertOk()->assertJsonPath('data.appearance.footer.tagline', 'Draft footer');
    }

    public function test_preview_resolves_media_seo_logo_and_shared_slider_like_public_content(): void
    {
        $actor = $this->manager();
        $media = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'media/preview.webp', 'mime_type' => 'image/webp', 'size' => 12, 'title' => 'Preview']);
        $slider = Slider::query()->create(['name' => 'Preview slider', 'slug' => 'preview-slider', 'is_published' => true]);
        $visible = Banner::query()->create(['title' => 'Visible slide', 'image_media_id' => $media->id, 'is_published' => true]);
        $hidden = Banner::query()->create(['title' => 'Hidden slide', 'is_published' => false]);
        $slider->banners()->attach([$hidden->id => ['position' => 0], $visible->id => ['position' => 1]]);
        $page = Page::query()->where('slug', 'home')->sole();
        $about = HomePage::query()->findOrFail(1)->content['about'];
        $about['image_media_id'] = $media->id;
        $seo = $page->seo;
        $seo['og_image_media_id'] = $media->id;
        $this->actingAs($actor)->patchJson('/api/v1/admin/pages/'.$page->id, ['blocks' => [
            ['id' => 'hero', 'type' => 'hero', 'enabled' => true, 'data' => ['slider_id' => $slider->id]],
            ['id' => 'about', 'type' => 'about', 'enabled' => true, 'data' => $about],
        ], 'seo' => $seo])->assertOk();
        $header = $page->site_layout['header'];
        $header['logo_media_id'] = $media->id;
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk();
        $url = Storage::disk('public')->url($media->path);
        $preview = $this->getJson('/api/v1/admin/content-preview/home')->assertOk()->assertJsonCount(1, 'data.page.blocks.0.data.slides')
            ->assertJsonPath('data.page.blocks.0.data.slides.0.image_url', $url)->assertJsonPath('data.page.blocks.1.data.image_url', $url)
            ->assertJsonPath('data.page.seo.og_image_url', $url)->assertJsonPath('data.appearance.header.logo_url', $url)->json('data.page');
        $this->postJson('/api/v1/admin/pages/'.$page->id.'/publish')->assertOk();
        $this->getJson('/api/v1/pages/home')->assertJsonPath('data.blocks', $preview['blocks'])->assertJsonPath('data.seo', $preview['seo']);
        $slider->update(['is_published' => false]);
        $this->getJson('/api/v1/admin/content-preview/home')->assertJsonCount(0, 'data.page.blocks.0.data.slides');
        $this->getJson('/api/v1/pages/home')->assertJsonCount(0, 'data.blocks.0.data.slides');
    }

    /** @param TestResponse<Response> $response */
    private function assertPrivate(TestResponse $response): void
    {
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Referrer-Policy', 'no-referrer');
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
