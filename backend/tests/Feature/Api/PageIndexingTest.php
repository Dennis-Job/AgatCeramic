<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PageIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_pages_keep_indexing_and_legacy_snapshots_are_not_marked_dirty(): void
    {
        $page = Page::query()->where('slug', 'about')->sole();
        $this->getJson('/api/v1/pages/about')->assertOk()->assertJsonPath('data.is_indexable', true);
        $this->actingAs($this->manager())->getJson('/api/v1/admin/pages/'.$page->id)
            ->assertOk()->assertJsonPath('data.is_indexable', true)->assertJsonPath('data.has_unpublished_changes', false);
    }

    public function test_new_pages_default_to_noindex_and_changes_only_reach_the_public_snapshot_after_publication(): void
    {
        $this->actingAs($this->manager());
        $id = $this->postJson('/api/v1/admin/pages', ['title' => 'Доставка', 'slug' => 'delivery', 'body' => 'Условия'])
            ->assertCreated()->assertJsonPath('data.is_indexable', false)->json('data.id');
        $url = '/api/v1/admin/pages/'.$id;
        $this->getJson('/api/v1/pages/delivery')->assertNotFound();
        $this->postJson($url.'/publish')->assertOk()->assertJsonPath('data.has_unpublished_changes', false);
        $this->getJson('/api/v1/pages/delivery')->assertOk()->assertJsonPath('data.is_indexable', false);
        $this->patchJson($url, ['is_indexable' => true])->assertOk()->assertJsonPath('data.has_unpublished_changes', true);
        $this->getJson('/api/v1/pages/delivery')->assertJsonPath('data.is_indexable', false);
        $this->postJson($url.'/publish')->assertOk()->assertJsonPath('data.has_unpublished_changes', false);
        $this->getJson('/api/v1/pages/delivery')->assertJsonPath('data.is_indexable', true);
        $this->patchJson($url, ['is_indexable' => false])->assertOk();
        $this->getJson('/api/v1/pages/delivery')->assertJsonPath('data.is_indexable', true);
        $this->postJson($url.'/publish')->assertOk();
        $this->getJson('/api/v1/pages')->assertOk()->assertJsonPath('data.4.is_indexable', false);
        $this->getJson('/api/v1/pages/delivery')->assertJsonPath('data.is_indexable', false);
    }

    public function test_indexing_can_be_selected_on_creation_and_validates_boolean_and_permissions(): void
    {
        $this->actingAs($this->manager());
        $this->postJson('/api/v1/admin/pages', ['title' => 'Гарантия', 'slug' => 'warranty', 'body' => 'Условия', 'is_indexable' => true])
            ->assertCreated()->assertJsonPath('data.is_indexable', true);
        $page = Page::query()->where('slug', 'about')->sole();
        $url = '/api/v1/admin/pages/'.$page->id;
        foreach ([null, 'yes', [], 2] as $value) {
            $this->patchJson($url, ['is_indexable' => $value])->assertUnprocessable()->assertJsonValidationErrors('is_indexable', 'error.details');
        }
        $this->actingAs($this->manager('analyst'))->patchJson($url, ['is_indexable' => false])->assertForbidden();
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'is_indexable' => true]);
    }

    public function test_homepage_and_appearance_publication_respect_page_indexing_snapshot(): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $this->getJson('/api/v1/home-page')->assertOk()->assertJsonPath('data.is_indexable', true);
        $this->patchJson('/api/v1/admin/pages/'.$home->id, ['is_indexable' => false])->assertOk();
        $this->getJson('/api/v1/admin/home-page')->assertJsonPath('data.is_indexable', false);
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.is_indexable', true);
        $this->postJson('/api/v1/admin/home-page/publish')->assertOk();
        $this->getJson('/api/v1/home-page')->assertJsonPath('data.is_indexable', false);
    }

    public function test_preview_service_slug_cannot_be_used_for_a_public_content_page(): void
    {
        $this->actingAs($this->manager());
        $this->postJson('/api/v1/admin/pages', ['title' => 'Страница', 'slug' => 'preview', 'body' => 'Текст'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug', 'error.details');
        $id = $this->postJson('/api/v1/admin/pages', ['title' => 'Страница', 'slug' => 'delivery', 'body' => 'Текст'])
            ->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/pages/'.$id, ['slug' => 'preview'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug', 'error.details');
    }

    public function test_migration_preserves_existing_rows_and_uses_noindex_as_the_database_default_for_new_rows(): void
    {
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('is_indexable'));
        $before = DB::table('pages')->orderBy('id')->get()->toArray();
        $migration = require database_path('migrations/2026_10_20_000000_add_page_indexing.php');
        $migration->up();
        foreach ($before as $row) {
            $after = (array) DB::table('pages')->where('id', $row->id)->first();
            self::assertTrue((bool) $after['is_indexable']);
            unset($after['is_indexable']);
            self::assertEquals((array) $row, $after);
        }
        $id = DB::table('pages')->insertGetId(['title' => 'Новая', 'slug' => 'new-raw-row', 'body' => 'Текст']);
        $this->assertDatabaseHas('pages', ['id' => $id, 'is_indexable' => false]);
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
