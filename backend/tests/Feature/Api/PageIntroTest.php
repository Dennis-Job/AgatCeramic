<?php

namespace Tests\Feature\Api;

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
use Tests\TestCase;

class PageIntroTest extends TestCase
{
    use RefreshDatabase;

    public function test_intro_is_a_saved_draft_until_published_and_omitted_updates_preserve_it(): void
    {
        $this->actingAs($actor = $this->manager());
        $page = Page::query()->where('slug', 'about')->sole();
        $url = '/api/v1/admin/pages/'.$page->id;
        $image = $this->image('intro');
        $intro = ['image_url' => null, 'image_media_id' => $image->id, 'image_alt' => 'Образцы керамики'];
        $this->getJson('/api/v1/pages/about')->assertOk()->assertJsonPath('data.intro', null);
        self::assertFalse(PageBlocks::hasUnpublishedChanges($page));

        $this->patchJson($url, ['intro' => $intro])->assertOk()->assertJsonPath('data.intro', $intro)->assertJsonPath('data.has_unpublished_changes', true);
        $this->patchJson($url, ['title' => $page->title])->assertOk()->assertJsonPath('data.intro', $intro);
        $this->getJson('/api/v1/admin/content-preview/about')->assertOk()
            ->assertJsonPath('data.page.intro.image_url', Storage::disk('public')->url($image->path))
            ->assertJsonPath('data.page.intro.image_alt', $intro['image_alt']);
        $this->getJson('/api/v1/pages/about')->assertJsonPath('data.intro', null);
        self::assertFalse(app(MediaManagementService::class)->deleteIfUnused($actor, $image->id));

        $this->postJson($url.'/publish')->assertOk()->assertJsonPath('data.has_unpublished_changes', false);
        $this->patchJson($url, ['intro' => null])->assertOk()->assertJsonPath('data.intro', null);
        $this->getJson('/api/v1/pages/about')->assertOk()->assertJsonPath('data.intro.image_media_id', $image->id);
        self::assertFalse(app(MediaManagementService::class)->deleteIfUnused($actor, $image->id));
        $this->postJson($url.'/publish')->assertOk();
        $this->getJson('/api/v1/pages/about')->assertJsonPath('data.intro', null);
        self::assertTrue(app(MediaManagementService::class)->deleteIfUnused($actor, $image->id));
    }

    public function test_intro_rejects_unknown_fields_unsafe_urls_and_non_public_images(): void
    {
        $this->actingAs($this->manager());
        $page = Page::query()->where('slug', 'about')->sole();
        $url = '/api/v1/admin/pages/'.$page->id;
        $base = ['image_url' => null, 'image_media_id' => null, 'image_alt' => ''];
        foreach ([
            [...$base, 'unknown' => true],
            [...$base, 'image_url' => 'javascript:alert(1)'],
            [...$base, 'image_url' => '//external.example/image.webp'],
            [...$base, 'image_media_id' => 999999],
            [...$base, 'image_alt' => str_repeat('x', 1001)],
            ['image_url' => '/images/sample.webp'],
        ] as $intro) {
            $this->patchJson($url, ['intro' => $intro])->assertUnprocessable();
        }
        $image = $this->image('private');
        $image->update(['disk' => 'local']);
        $this->patchJson($url, ['intro' => [...$base, 'image_media_id' => $image->id]])->assertUnprocessable();
        $image->update(['disk' => 'public', 'kind' => 'document']);
        $this->patchJson($url, ['intro' => [...$base, 'image_media_id' => $image->id]])->assertUnprocessable();
        $this->patchJson($url, ['intro' => $base])->assertOk()->assertJsonPath('data.intro.image_alt', '');
        $this->actingAs($this->manager('analyst'))->patchJson($url, ['intro' => $base])->assertForbidden();
    }

    public function test_create_supports_intro_and_url_only_references_protect_original_and_thumbnail(): void
    {
        $this->actingAs($actor = $this->manager());
        $image = $this->image('url');
        $intro = ['image_url' => '/storage/'.$image->path, 'image_media_id' => null, 'image_alt' => 'Материалы'];
        $id = $this->postJson('/api/v1/admin/pages', ['title' => 'Материалы', 'slug' => 'intro-materials', 'body' => 'Текст страницы', 'intro' => $intro])
            ->assertCreated()->assertJsonPath('data.intro', $intro)->json('data.id');
        self::assertFalse(app(MediaManagementService::class)->deleteIfUnused($actor, $image->id));
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish')->assertOk();
        $this->patchJson('/api/v1/admin/pages/'.$id, ['intro' => null])->assertOk();
        self::assertFalse(app(MediaManagementService::class)->deleteIfUnused($actor, $image->id));
        $this->postJson('/api/v1/admin/pages/'.$id.'/publish')->assertOk();
        $intro['image_url'] = 'https://shop.example.test/storage/'.$image->thumbnail_path.'?v=1';
        $this->patchJson('/api/v1/admin/pages/'.$id, ['intro' => $intro])->assertOk();
        self::assertFalse(app(MediaManagementService::class)->deleteIfUnused($actor, $image->id));
    }

    private function image(string $name): Media
    {
        return Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'media/'.$name.'.webp', 'thumbnail_path' => 'media/'.$name.'-thumb.webp', 'mime_type' => 'image/webp', 'size' => 12, 'title' => 'Керамика']);
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
