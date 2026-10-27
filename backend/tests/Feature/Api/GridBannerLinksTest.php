<?php

namespace Tests\Feature\Api;

use App\Models\HomePage;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GridBannerLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_grid_links_survive_draft_publication_without_changing_the_menu(): void
    {
        $this->actingAs($this->manager());
        $content = HomePage::query()->findOrFail(1)->content;
        $grid = $content['categories'];
        $menu = $content['materials'];
        $grid['items'][0]['link_url'] = '/catalog/tiles?style=stone#products';
        $grid['items'][1]['link_url'] = 'https://example.com/catalog';
        self::assertSame($grid['items'][0]['id'], $menu['items'][0]['id']);

        $this->patchJson('/api/v1/admin/home-page', ['categories' => $grid])->assertOk()
            ->assertJsonPath('data.categories.items.0.link_url', $grid['items'][0]['link_url'])
            ->assertJsonPath('data.materials', $menu);
        $this->getJson('/api/v1/home-page')->assertJsonMissingPath('data.categories.items.0.link_url');
        $this->postJson('/api/v1/admin/home-page/publish')->assertOk();
        $this->getJson('/api/v1/home-page')->assertOk()
            ->assertJsonPath('data.categories.items.0.link_url', $grid['items'][0]['link_url'])
            ->assertJsonPath('data.categories.items.1.link_url', $grid['items'][1]['link_url'])
            ->assertJsonPath('data.materials', $menu);
        $this->getJson('/api/v1/admin/home-page')->assertOk()
            ->assertJsonPath('data.categories.items.1.link_url', $grid['items'][1]['link_url']);
    }

    public function test_page_blocks_preserve_each_grid_link_and_accept_absent_null_or_empty_links(): void
    {
        $this->actingAs($this->manager());
        $page = Page::query()->where('slug', 'about')->sole();
        $base = '/api/v1/admin/pages/'.$page->id;
        $content = HomePage::query()->findOrFail(1)->content;
        $grid = $content['categories'];
        $menu = $content['materials'];
        $grid['items'][0]['link_url'] = '/catalog';
        $grid['items'][1]['link_url'] = 'https://example.com/catalog';
        $grid['items'][2]['link_url'] = null;
        $grid['items'][3] = [...$grid['items'][0], 'id' => 'empty-link', 'link_url' => ''];
        $grid['items'][] = [...$grid['items'][0], 'id' => 'without-link'];
        unset($grid['items'][4]['link_url']);
        $blocks = [
            ['id' => 'grid', 'type' => 'categories', 'enabled' => true, 'data' => $grid],
            ['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => $menu],
        ];
        $blocks[0]['data']['items'][3]['link_url'] = null;
        $input = $blocks;
        $input[0]['data']['items'][3]['link_url'] = '';
        $this->patchJson($base, ['blocks' => $input])->assertOk()->assertJsonPath('data.blocks', $blocks);
        $this->getJson('/api/v1/pages/about')->assertJsonMissingPath('data.blocks.0.data.items.0.link_url');
        $this->postJson($base.'/publish')->assertOk();
        $this->getJson('/api/v1/pages/about')->assertOk()->assertJsonPath('data.blocks', $blocks);
        $this->getJson($base)->assertOk()->assertJsonPath('data.blocks', $blocks);

        unset($grid['items'][0]['link_url']);
        $grid['items'][1]['link_url'] = null;
        $this->patchJson('/api/v1/admin/home-page', ['categories' => $grid])->assertOk()
            ->assertJsonMissingPath('data.categories.items.0.link_url')
            ->assertJsonPath('data.categories.items.1.link_url', null)
            ->assertJsonPath('data.categories.items.3.link_url', null);
    }

    public function test_grid_rejects_unsafe_links_in_both_editing_routes(): void
    {
        $this->actingAs($this->manager());
        $page = Page::query()->where('slug', 'about')->sole();
        $grid = HomePage::query()->findOrFail(1)->content['categories'];
        foreach (['javascript:alert(1)', '//evil.example/path', 'https://user:pass@example.com', '/\\evil.example', "/catalog\x01hidden", "https://example.com/a\nb", 'http://example.com', 'catalog/tiles'] as $url) {
            $grid['items'][0]['link_url'] = $url;
            $this->patchJson('/api/v1/admin/home-page', ['categories' => $grid])->assertUnprocessable()
                ->assertJsonValidationErrors('categories.items.0.link_url', 'error.details');
            $this->patchJson('/api/v1/admin/pages/'.$page->id, ['blocks' => [
                ['id' => 'grid', 'type' => 'categories', 'enabled' => true, 'data' => $grid],
            ]])->assertUnprocessable()->assertJsonValidationErrors('blocks.0.data.items.0.link_url', 'error.details');
        }
    }

    public function test_independent_menu_also_accepts_its_own_link_field(): void
    {
        $this->actingAs($this->manager());
        $page = Page::query()->where('slug', 'about')->sole();
        $menu = HomePage::query()->findOrFail(1)->content['materials'];
        $menu['items'][0]['link_url'] = '/catalog';
        $this->patchJson('/api/v1/admin/home-page', ['materials' => $menu])->assertOk()
            ->assertJsonPath('data.materials.items.0.link_url', '/catalog');
        $this->patchJson('/api/v1/admin/pages/'.$page->id, ['blocks' => [
            ['id' => 'menu', 'type' => 'materials', 'enabled' => true, 'data' => $menu],
        ]])->assertOk()->assertJsonPath('data.blocks.0.data.items.0.link_url', '/catalog');
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
