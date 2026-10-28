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

class NavigationSubmenusTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('writePaths')]
    public function test_mixed_navigation_roundtrips_in_saved_preview_without_publishing_the_draft(string $path): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $published = $home->published_snapshot;
        $header = $this->header();
        $header['navigation'] = [
            ['label' => 'О компании', 'to' => '/about'],
            ['label' => 'Каталог', 'children' => [
                ['label' => 'Весь каталог', 'to' => '/catalog'],
                ['label' => 'Коллекции', 'to' => 'https://example.com/collections'],
            ]],
        ];
        $this->patchJson($path, ['header' => $header])->assertOk()
            ->assertJsonPath('data.header.navigation', $header['navigation']);
        foreach (['/api/v1/admin/home-page', '/api/v1/admin/site-appearance'] as $readPath) {
            $this->getJson($readPath)->assertOk()->assertJsonPath('data.header.navigation', $header['navigation']);
        }
        foreach (['home', 'about'] as $slug) {
            $this->getJson('/api/v1/admin/content-preview/'.$slug)->assertOk()
                ->assertJsonPath('data.appearance.header.navigation', $header['navigation']);
        }
        self::assertSame($header['navigation'], $home->refresh()->site_layout['header']['navigation']);
        self::assertSame($header['navigation'], HomePage::query()->findOrFail(1)->content['header']['navigation']);
        self::assertSame($published, $home->published_snapshot);
        foreach (['/api/v1/home-page', '/api/v1/site-appearance'] as $publicPath) {
            $this->getJson($publicPath)->assertOk()
                ->assertJsonPath('data.header.navigation', $published['site_layout']['header']['navigation']);
        }
        $this->postJson('/api/v1/admin/pages/'.$home->id.'/publish')->assertOk();
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.header.navigation', $published['site_layout']['header']['navigation']);
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        foreach (['/api/v1/home-page', '/api/v1/site-appearance'] as $publicPath) {
            $this->getJson($publicPath)->assertOk()->assertJsonPath('data.header.navigation', $header['navigation']);
        }
        self::assertSame($header['navigation'], app(PublishedPageQuery::class)->get('home')['site_layout']['header']['navigation']);
        $this->assertDatabaseHas('audit_logs', ['action' => $path === '/api/v1/admin/home-page' ? 'home-page.updated' : 'site-appearance.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site-appearance.published']);
    }

    /** @return array<string, array{string}> */
    public static function writePaths(): array
    {
        return ['appearance' => ['/api/v1/admin/site-appearance'], 'legacy' => ['/api/v1/admin/home-page']];
    }

    public function test_legacy_links_and_maximum_size_groups_keep_their_order_and_shapes(): void
    {
        $this->actingAs($this->manager());
        $header = $this->header();
        $legacy = [['label' => 'О компании', 'to' => '/about']];
        $header['navigation'] = $legacy;
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['header' => $header])->assertOk()->assertJsonPath('data.header.navigation', $legacy);
        }
        $children = array_fill(0, 16, ['label' => str_repeat('Я', 255), 'to' => '/'.str_repeat('a', 2047)]);
        $header['navigation'] = array_fill(0, 16, ['label' => str_repeat('Я', 255), 'children' => $children]);
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk()
            ->assertJsonPath('data.header.navigation', $header['navigation']);
    }

    #[DataProvider('invalidNavigation')]
    public function test_invalid_navigation_is_rejected_by_both_write_routes_without_changing_storage(array $navigation, string $field): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $before = $home->site_layout;
        $legacy = HomePage::query()->findOrFail(1)->content;
        $header = $this->header();
        $header['navigation'] = $navigation;
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['header' => $header])->assertUnprocessable()
                ->assertJsonValidationErrors('header.navigation'.$field, 'error.details');
        }
        self::assertSame($before, $home->refresh()->site_layout);
        self::assertSame($legacy, HomePage::query()->findOrFail(1)->content);
    }

    /** @return array<string, array{array<mixed>, string}> */
    public static function invalidNavigation(): array
    {
        $link = ['label' => 'Плитка', 'to' => '/catalog'];
        $group = ['label' => 'Каталог', 'children' => [$link]];
        $cases = [
            'empty menu' => [[], ''],
            'too many parents' => [array_fill(0, 17, $link), ''],
            'non-list parents' => [['group' => $group], ''],
            'mixed mode' => [[['to' => '/catalog', ...$group]], '.0.to'],
            'null inactive URL' => [[['to' => null, ...$group]], '.0.to'],
            'empty group' => [[['label' => 'Каталог', 'children' => []]], '.0.children'],
            'null children' => [[['label' => 'Каталог', 'children' => null]], '.0.children'],
            'scalar children' => [[['label' => 'Каталог', 'children' => 'bad']], '.0.children'],
            'non-list children' => [[['label' => 'Каталог', 'children' => ['tile' => $link]]], '.0.children'],
            'too many children' => [[['label' => 'Каталог', 'children' => array_fill(0, 17, $link)]], '.0.children'],
            'deep nesting' => [[['label' => 'Каталог', 'children' => [$group]]], '.0.children.0'],
            'unknown parent field' => [[['unknown' => true, ...$group]], '.0'],
            'unknown child field' => [[['label' => 'Каталог', 'children' => [['extra' => true, ...$link]]]], '.0.children.0'],
            'missing parent label' => [[['children' => [$link]]], '.0.label'],
            'long parent label' => [[['label' => str_repeat('я', 256), 'children' => [$link]]], '.0.label'],
            'missing link URL' => [[['label' => 'Плитка']], '.0.to'],
            'missing child label' => [[['label' => 'Каталог', 'children' => [['to' => '/catalog']]]], '.0.children.0.label'],
            'empty child label' => [[['label' => 'Каталог', 'children' => [['label' => '', 'to' => '/catalog']]]], '.0.children.0.label'],
            'long child label' => [[['label' => 'Каталог', 'children' => [['label' => str_repeat('я', 256), 'to' => '/catalog']]]], '.0.children.0.label'],
            'missing child URL' => [[['label' => 'Каталог', 'children' => [['label' => 'Плитка']]]], '.0.children.0.to'],
        ];
        foreach (['', 'javascript:alert(1)', 'http://example.com', '//example.com', 'https://user:pass@example.com', '/bad path', '/bad\\path', "/bad\npath", '/'.str_repeat('a', 2048)] as $index => $url) {
            $cases['unsafe child URL '.$index] = [[['label' => 'Каталог', 'children' => [['label' => 'Плитка', 'to' => $url]]]], '.0.children.0.to'];
        }

        return $cases;
    }

    public function test_groups_do_not_weaken_footer_validation_or_appearance_permissions(): void
    {
        $this->getJson('/api/v1/admin/content-preview/home')->assertUnauthorized();
        $this->actingAs($this->manager());
        $header = $this->header();
        $header['navigation'] = [['label' => 'Каталог', 'children' => [['label' => 'Плитка', 'to' => '/catalog']]]];
        $footer = $this->getJson('/api/v1/admin/site-appearance')->json('data.footer');
        $footer['explore_links'] = $header['navigation'];
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['footer' => $footer])->assertUnprocessable();
        }
        $home = Page::query()->where('slug', 'home')->sole();
        $this->patchJson('/api/v1/admin/pages/'.$home->id, ['site_layout' => ['header' => $header]])
            ->assertUnprocessable()->assertJsonValidationErrors('site_layout', 'error.details');
        $this->actingAs($this->manager('analyst'));
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['header' => $header])->assertForbidden();
        }
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertForbidden();
        $this->getJson('/api/v1/admin/content-preview/home')->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function header(): array
    {
        $header = $this->getJson('/api/v1/admin/site-appearance')->assertOk()->json('data.header');
        unset($header['logo_url']);

        return $header;
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
