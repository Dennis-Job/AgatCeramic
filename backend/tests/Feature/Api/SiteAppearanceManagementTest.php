<?php

namespace Tests\Feature\Api;

use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\MediaManagementService;
use App\Services\SiteAppearanceManagementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SiteAppearanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_appearance_and_home_content_are_saved_and_published_independently(): void
    {
        $actor = $this->userWithRole('content-manager');
        $home = Page::query()->where('slug', 'home')->firstOrFail();
        $before = $home->published_snapshot;
        $header = $this->actingAs($actor)->getJson('/api/v1/admin/site-appearance')
            ->assertOk()->assertJsonPath('data.has_unpublished_changes', false)->json('data.header');
        unset($header['logo_url']);
        $header['topbar_left'] = 'Новое оформление';
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])
            ->assertOk()->assertJsonPath('data.has_unpublished_changes', true);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site-appearance.updated', 'entity_id' => 1]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'home-page.updated']);
        $this->getJson('/api/v1/admin/pages/'.$home->id)->assertJsonPath('data.has_unpublished_changes', false);
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.header.topbar_left', $before['site_layout']['header']['topbar_left']);

        $this->patchJson('/api/v1/admin/pages/'.$home->id, ['title' => 'Новый черновик главной'])->assertOk();
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk()->assertJsonPath('data.has_unpublished_changes', false);
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.header.topbar_left', 'Новое оформление');
        $this->getJson('/api/v1/admin/pages/'.$home->id)->assertJsonPath('data.has_unpublished_changes', true);
        $home->refresh();
        self::assertSame($before['title'], $home->published_snapshot['title']);
        self::assertSame($before['blocks'], $home->published_snapshot['blocks']);
        self::assertSame($before['seo'], $home->published_snapshot['seo']);

        $header['topbar_left'] = 'Следующий черновик оформления';
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk();
        $this->postJson('/api/v1/admin/pages/'.$home->id.'/publish')->assertOk()->assertJsonPath('data.has_unpublished_changes', false);
        $this->getJson('/api/v1/admin/site-appearance')->assertJsonPath('data.has_unpublished_changes', true);
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.header.topbar_left', 'Новое оформление');
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.header.topbar_left', 'Новое оформление');
        $this->assertDatabaseHas('audit_logs', ['action' => 'site-appearance.published', 'entity_id' => $home->id]);
    }

    public function test_public_appearance_survives_home_unpublication_without_republishing_it(): void
    {
        $actor = $this->userWithRole('content-manager');
        $home = Page::query()->where('slug', 'home')->firstOrFail();
        $publishedAt = $home->published_at;
        $this->actingAs($actor)->patchJson('/api/v1/admin/pages/'.$home->id, ['is_published' => false])->assertOk();
        $this->getJson('/api/v1/home-page')->assertNotFound();
        $this->getJson('/api/v1/site-appearance')->assertOk()->assertJsonMissingPath('data.blocks')->assertJsonMissingPath('data.has_unpublished_changes');
        $footer = $this->getJson('/api/v1/admin/site-appearance')->json('data.footer');
        $footer['tagline'] = 'Новый подвал';
        $this->patchJson('/api/v1/admin/site-appearance', ['footer' => $footer])->assertOk();
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.footer.tagline', 'Новый подвал');
        $this->getJson('/api/v1/home-page')->assertNotFound();
        $home->refresh();
        self::assertFalse($home->is_published);
        self::assertTrue($publishedAt->equalTo($home->published_at));
    }

    public function test_authorization_and_safe_layout_validation_are_enforced(): void
    {
        $this->getJson('/api/v1/admin/site-appearance')->assertUnauthorized();
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertUnauthorized();
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/site-appearance')->assertForbidden();
        $this->patchJson('/api/v1/admin/site-appearance', [])->assertForbidden();
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertForbidden();
        $this->actingAs($this->userWithRole('content-manager'));
        $header = $this->getJson('/api/v1/admin/site-appearance')->json('data.header');
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertUnprocessable();
        unset($header['logo_url']);
        $header['navigation'][0]['to'] = 'javascript:alert(1)';
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertUnprocessable();
        $header['navigation'][0]['to'] = '/catalog';
        $header['logo_media_id'] = 999999;
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertUnprocessable();
    }

    public function test_logo_projection_and_draft_and_published_media_removal_protection(): void
    {
        $actor = $this->userWithRole('content-manager');
        $media = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'media/logo.webp', 'mime_type' => 'image/webp', 'size' => 12, 'title' => 'Логотип']);
        $header = $this->actingAs($actor)->getJson('/api/v1/admin/site-appearance')->json('data.header');
        unset($header['logo_url']);
        $header['logo_media_id'] = $media->id;
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk()
            ->assertJsonPath('data.header.logo_url', Storage::disk('public')->url('media/logo.webp'));
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        $header['logo_media_id'] = null;
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk();
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.header.logo_url', Storage::disk('public')->url('media/logo.webp'));
        try {
            app(MediaManagementService::class)->delete($actor, $media);
            self::fail('Published appearance logo must remain protected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('media', $exception->errors());
        }
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        app(MediaManagementService::class)->delete($actor, $media);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_unknown_top_level_fields_cannot_change_home_page_content(): void
    {
        $actor = $this->userWithRole('content-manager');
        $home = Page::query()->where('slug', 'home')->firstOrFail();
        $legacy = HomePage::query()->findOrFail(1);
        $pageFields = ['title', 'blocks', 'seo', 'site_layout', 'is_published', 'published_snapshot', 'published_at'];
        $before = $home->only($pageFields);
        $legacyBefore = $legacy->only(['hero_slider_id', 'content']);
        $this->actingAs($actor)->patchJson('/api/v1/admin/site-appearance', [
            'title' => 'Unauthorized page title',
            'hero_slider_id' => null,
            'blocks' => [],
            'seo' => ['title' => 'Unauthorized SEO'],
            'is_published' => false,
            'published_snapshot' => ['title' => 'Unauthorized snapshot'],
        ])->assertOk()->assertJsonPath('data.has_unpublished_changes', false);

        self::assertEquals($before, $home->refresh()->only($pageFields));
        self::assertSame($legacyBefore, $legacy->refresh()->only(['hero_slider_id', 'content']));
    }

    public function test_appearance_update_rolls_back_if_audit_cannot_be_saved(): void
    {
        $actor = $this->userWithRole('content-manager');
        $home = Page::query()->where('slug', 'home')->firstOrFail();
        $legacy = HomePage::query()->findOrFail(1);
        $layoutBefore = $home->site_layout;
        $contentBefore = $legacy->content;
        $header = $layoutBefore['header'];
        $header['topbar_left'] = 'Must roll back';
        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->withArgs(fn ($actor, $action) => $action === 'site-appearance.updated')
                ->andThrow(new \RuntimeException('Audit unavailable'));
        });

        try {
            app(SiteAppearanceManagementService::class)->update($actor, ['header' => $header]);
            self::fail('Appearance update must fail when audit persistence fails.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Audit unavailable', $exception->getMessage());
        }

        self::assertSame($layoutBefore, $home->refresh()->site_layout);
        self::assertSame($contentBefore, $legacy->refresh()->content);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'site-appearance.updated']);
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
