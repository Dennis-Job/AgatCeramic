<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Support\PageBlocks;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InformationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_pages_are_public_and_remain_editable_as_cms_drafts(): void
    {
        foreach (['delivery' => 'Доставка', 'payment' => 'Оплата', 'designers' => 'Дизайнерам'] as $slug => $title) {
            $this->getJson('/api/v1/pages/'.$slug)->assertOk()
                ->assertJsonPath('data.title', $title)
                ->assertJsonPath('data.intro', null)
                ->assertJsonPath('data.is_indexable', false)
                ->assertJsonPath('data.blocks.0.type', 'text');
            self::assertFalse(PageBlocks::hasUnpublishedChanges(Page::query()->where('slug', $slug)->sole()));
        }

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::query()->where('slug', 'content-manager')->sole());
        $this->actingAs($actor);
        $page = Page::query()->where('slug', 'delivery')->sole();
        $this->patchJson('/api/v1/admin/pages/'.$page->id, ['title' => 'Условия доставки'])
            ->assertOk()->assertJsonPath('data.title', 'Условия доставки')
            ->assertJsonPath('data.has_unpublished_changes', true);
        $this->getJson('/api/v1/pages/delivery')->assertJsonPath('data.title', 'Доставка');
    }

    public function test_rerunning_provisioning_preserves_existing_drafts_and_published_addresses(): void
    {
        Page::query()->where('slug', 'delivery')->sole()->update(['title' => 'Свой черновик', 'is_published' => false, 'published_slug' => null, 'published_snapshot' => null]);
        Page::query()->where('slug', 'payment')->sole()->update(['slug' => 'payment-renamed', 'title' => 'Свой заголовок']);
        $before = DB::table('pages')->orderBy('id')->get()->toArray();
        $migration = $this->provisioningMigration();
        $migration->up();
        $migration->up();
        self::assertEquals($before, DB::table('pages')->orderBy('id')->get()->toArray());
        self::assertSame(0, Page::query()->where('slug', 'payment')->count());
        $this->getJson('/api/v1/pages/payment')->assertOk()->assertJsonPath('data.title', 'Оплата');
        $this->getJson('/api/v1/pages/delivery')->assertNotFound();
    }

    public function test_provisioning_only_creates_missing_pages_and_down_retains_editable_content(): void
    {
        Page::query()->whereIn('slug', ['delivery', 'payment', 'designers'])->delete();
        $existing = DB::table('pages')->orderBy('id')->get()->toArray();
        $migration = $this->provisioningMigration();
        $migration->up();
        self::assertSame(count($existing) + 3, Page::query()->count());
        self::assertEquals($existing, DB::table('pages')->whereNotIn('slug', ['delivery', 'payment', 'designers'])->orderBy('id')->get()->toArray());
        $snapshot = DB::table('pages')->orderBy('id')->get()->toArray();
        $migration->down();
        self::assertEquals($snapshot, DB::table('pages')->orderBy('id')->get()->toArray());
    }

    private function provisioningMigration(): Migration
    {
        return require database_path('migrations/2026_11_10_000002_add_information_pages.php');
    }
}
