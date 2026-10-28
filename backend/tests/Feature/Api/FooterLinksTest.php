<?php

namespace Tests\Feature\Api;

use App\Models\HomePage;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Queries\PublishedPageQuery;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FooterLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_footer_navigation_is_projected_without_mutating_saved_layouts(): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $legacy = HomePage::query()->findOrFail(1);
        $header = $this->getJson('/api/v1/admin/site-appearance')->json('data.header');
        unset($header['logo_url']);
        $header['navigation'] = [
            ['label' => 'Компания', 'to' => '/about'],
            ['label' => 'Каталог', 'children' => [
                ['label' => 'Плитка', 'to' => '/catalog'],
                ['label' => 'Повтор', 'to' => '/about'],
            ]],
        ];
        $expected = [['label' => 'Компания', 'to' => '/about'], ['label' => 'Плитка', 'to' => '/catalog']];
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk();
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        $before = $home->refresh()->only(['site_layout', 'published_snapshot']);
        $legacyBefore = $legacy->refresh()->content;
        foreach (['/api/v1/site-appearance', '/api/v1/home-page', '/api/v1/admin/site-appearance', '/api/v1/admin/home-page'] as $path) {
            $this->getJson($path)->assertOk()->assertJsonPath('data.footer.navigation_links', $expected)
                ->assertJsonPath('data.footer.social_links', [])->assertJsonPath('data.footer.bottom_right_url', '');
        }
        $this->getJson('/api/v1/admin/content-preview/about')->assertOk()->assertJsonPath('data.appearance.footer.navigation_links', $expected);
        self::assertSame($expected, app(PublishedPageQuery::class)->get('home')['site_layout']['footer']['navigation_links']);
        self::assertSame($before, $home->refresh()->only(['site_layout', 'published_snapshot']));
        self::assertSame($legacyBefore, $legacy->refresh()->content);
    }

    #[DataProvider('writePaths')]
    public function test_footer_links_roundtrip_through_draft_preview_and_independent_publication(string $path): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $published = $this->getJson('/api/v1/site-appearance')->json('data.footer');
        $footer = $this->footer();
        $footer['navigation_links'] = [['label' => 'Доставка', 'to' => '/delivery'], ['label' => 'О нас', 'to' => '/about']];
        $footer['social_links'] = [['label' => 'Telegram', 'to' => 'https://t.me/example']];
        $footer['bottom_right'] = 'Политика конфиденциальности';
        $footer['bottom_right_url'] = '/privacy';
        $this->patchJson($path, ['footer' => $footer])->assertOk()->assertJsonPath('data.footer', $footer);
        $this->getJson('/api/v1/admin/content-preview/home')->assertOk()->assertJsonPath('data.appearance.footer', $footer);
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.footer', $published);
        $this->postJson('/api/v1/admin/pages/'.$home->id.'/publish')->assertOk();
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.footer', $published);
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        foreach (['/api/v1/site-appearance', '/api/v1/home-page'] as $readPath) {
            $this->getJson($readPath)->assertOk()->assertJsonPath('data.footer', $footer);
        }
        self::assertSame($footer['navigation_links'], app(PublishedPageQuery::class)->get('home')['site_layout']['footer']['navigation_links']);
        $header = $this->getJson('/api/v1/admin/site-appearance')->json('data.header');
        unset($header['logo_url']);
        $header['navigation'] = [['label' => 'Новая шапка', 'to' => '/new']];
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk()->assertJsonPath('data.footer.navigation_links', $footer['navigation_links']);
    }

    public function test_empty_lists_and_optional_url_are_preserved_and_old_writers_do_not_clear_new_fields(): void
    {
        $this->actingAs($this->manager());
        $footer = $this->footer();
        $footer['navigation_links'] = [];
        $footer['social_links'] = [];
        $footer['explore_links'] = [];
        $footer['bottom_right_url'] = '';
        $this->patchJson('/api/v1/admin/site-appearance', ['footer' => $footer])->assertOk()->assertJsonPath('data.footer.navigation_links', [])
            ->assertJsonPath('data.footer.social_links', [])->assertJsonPath('data.footer.explore_links', [])->assertJsonPath('data.footer.bottom_right_url', '');
        $footer['social_links'] = [['label' => 'Сообщество', 'to' => 'https://example.com/community']];
        $footer['bottom_right_url'] = '/privacy';
        $this->patchJson('/api/v1/admin/site-appearance', ['footer' => $footer])->assertOk();
        unset($footer['navigation_links'], $footer['social_links'], $footer['bottom_right_url']);
        $this->patchJson('/api/v1/admin/home-page', ['footer' => $footer])->assertOk()->assertJsonPath('data.footer.navigation_links', [])
            ->assertJsonPath('data.footer.social_links.0.label', 'Сообщество')->assertJsonPath('data.footer.bottom_right_url', '/privacy');
    }

    public function test_maximum_legacy_projection_can_be_saved_without_truncation(): void
    {
        $this->actingAs($this->manager());
        $header = $this->getJson('/api/v1/admin/site-appearance')->json('data.header');
        unset($header['logo_url']);
        $header['navigation'] = [];
        for ($group = 0; $group < 16; $group++) {
            $children = [];
            for ($index = 0; $index < 16; $index++) {
                $children[] = ['label' => 'Ссылка '.$group.'-'.$index, 'to' => '/catalog/'.$group.'/'.$index];
            }
            $header['navigation'][] = ['label' => 'Раздел '.$group, 'children' => $children];
        }
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk();
        $footer = $this->footer();
        self::assertCount(256, $footer['navigation_links']);
        $footer['social_links'] = array_fill(0, 12, ['label' => str_repeat('я', 255), 'to' => '/'.str_repeat('a', 2047)]);
        $footer['bottom_right_url'] = null;
        $this->patchJson('/api/v1/admin/site-appearance', ['footer' => $footer])->assertOk()
            ->assertJsonPath('data.footer.navigation_links', $footer['navigation_links'])
            ->assertJsonPath('data.footer.social_links', $footer['social_links'])->assertJsonPath('data.footer.bottom_right_url', '');
        $footer['navigation_links'] = [];
        $footer['social_links'] = [];
        $this->patchJson('/api/v1/admin/site-appearance', ['footer' => $footer])->assertOk();
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        $this->getJson('/api/v1/site-appearance')->assertOk()->assertJsonPath('data.footer.navigation_links', [])
            ->assertJsonPath('data.footer.social_links', [])->assertJsonPath('data.footer.bottom_right_url', '');
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_new_fields_are_rejected_without_writing(string $field, mixed $value): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $before = $home->site_layout;
        $footer = $this->footer();
        $footer[$field] = $value;
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['footer' => $footer])->assertUnprocessable();
        }
        self::assertSame($before, $home->refresh()->site_layout);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidFields(): array
    {
        $link = ['label' => 'Плитка', 'to' => '/catalog'];
        $cases = [
            'navigation limit' => ['navigation_links', array_fill(0, 257, $link)],
            'social limit' => ['social_links', array_fill(0, 13, $link)],
            'navigation not list' => ['navigation_links', ['one' => $link]],
            'social not list' => ['social_links', ['one' => $link]],
            'navigation group' => ['navigation_links', [['label' => 'Каталог', 'children' => [$link]]]],
            'social unknown field' => ['social_links', [['label' => 'Соцсеть', 'to' => '/social', 'extra' => true]]],
            'navigation missing label' => ['navigation_links', [['to' => '/catalog']]],
            'social missing url' => ['social_links', [['label' => 'Соцсеть']]],
            'navigation long label' => ['navigation_links', [['label' => str_repeat('я', 256), 'to' => '/catalog']]],
        ];
        foreach (['javascript:alert(1)', '//example.com', 'http://example.com', 'https://user:pass@example.com', '/bad path', '/bad\\path', "/bad\npath", '/'.str_repeat('a', 2048)] as $index => $url) {
            $cases['bottom unsafe '.$index] = ['bottom_right_url', $url];
            $cases['navigation unsafe '.$index] = ['navigation_links', [['label' => 'Ссылка', 'to' => $url]]];
            $cases['social unsafe '.$index] = ['social_links', [['label' => 'Ссылка', 'to' => $url]]];
        }

        return $cases;
    }

    public function test_footer_changes_require_content_manage(): void
    {
        $this->actingAs($this->manager());
        $footer = $this->footer();
        $footer['navigation_links'] = [];
        $this->actingAs($this->manager('analyst'));
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['footer' => $footer])->assertForbidden();
        }
    }

    /** @return array<string, array{string}> */
    public static function writePaths(): array
    {
        return ['appearance' => ['/api/v1/admin/site-appearance'], 'legacy' => ['/api/v1/admin/home-page']];
    }

    /** @return array<string, mixed> */
    private function footer(): array
    {
        return $this->getJson('/api/v1/admin/site-appearance')->assertOk()->json('data.footer');
    }

    private function manager(string $role = 'content-manager'): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::query()->where('slug', $role)->sole());

        return $actor;
    }
}
