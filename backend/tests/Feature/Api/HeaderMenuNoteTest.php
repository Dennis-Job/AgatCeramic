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

class HeaderMenuNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_note_falls_back_to_tagline_without_mutating_storage(): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $legacy = HomePage::query()->findOrFail(1);
        $before = $home->only(['site_layout', 'published_snapshot']);
        $legacyBefore = $legacy->content;
        $tagline = $legacyBefore['footer']['tagline'];
        foreach (['/api/v1/site-appearance', '/api/v1/home-page', '/api/v1/admin/site-appearance', '/api/v1/admin/home-page'] as $path) {
            $this->getJson($path)->assertOk()->assertJsonPath('data.header.menu_note', $tagline);
        }
        foreach (['home', 'about'] as $slug) {
            $this->getJson('/api/v1/admin/content-preview/'.$slug)->assertOk()->assertJsonPath('data.appearance.header.menu_note', $tagline);
        }
        self::assertSame($tagline, app(PublishedPageQuery::class)->get('home')['site_layout']['header']['menu_note']);
        self::assertSame($before, $home->refresh()->only(['site_layout', 'published_snapshot']));
        self::assertSame($legacyBefore, $legacy->refresh()->content);
    }

    #[DataProvider('writePaths')]
    public function test_note_roundtrips_in_saved_preview_and_publishes_independently(string $path): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $published = $this->getJson('/api/v1/site-appearance')->json('data.header.menu_note');
        $header = $this->header();
        $header['menu_note'] = "Керамика для вашего дома.\nПоможем с выбором.";
        $this->patchJson($path, ['header' => $header])->assertOk()->assertJsonPath('data.header.menu_note', $header['menu_note']);
        foreach (['/api/v1/admin/site-appearance', '/api/v1/admin/home-page'] as $readPath) {
            $this->getJson($readPath)->assertOk()->assertJsonPath('data.header.menu_note', $header['menu_note']);
        }
        $this->getJson('/api/v1/admin/content-preview/about')->assertOk()->assertJsonPath('data.appearance.header.menu_note', $header['menu_note']);
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.header.menu_note', $published);
        $this->postJson('/api/v1/admin/pages/'.$home->id.'/publish')->assertOk();
        $this->getJson('/api/v1/site-appearance')->assertJsonPath('data.header.menu_note', $published);
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        foreach (['/api/v1/site-appearance', '/api/v1/home-page'] as $readPath) {
            $this->getJson($readPath)->assertOk()->assertJsonPath('data.header.menu_note', $header['menu_note']);
        }
        self::assertSame($header['menu_note'], app(PublishedPageQuery::class)->get('home')['site_layout']['header']['menu_note']);
    }

    public function test_empty_and_null_notes_disable_the_note_and_old_writers_preserve_it(): void
    {
        $this->actingAs($this->manager());
        $header = $this->header();
        foreach (['', null] as $note) {
            $header['menu_note'] = $note;
            foreach (array_column(self::writePaths(), 0) as $path) {
                $this->patchJson($path, ['header' => $header])->assertOk()->assertJsonPath('data.header.menu_note', '');
            }
        }
        $this->postJson('/api/v1/admin/site-appearance/publish')->assertOk();
        $this->getJson('/api/v1/site-appearance')->assertOk()->assertJsonPath('data.header.menu_note', '');
        $header['menu_note'] = str_repeat('я', 1000);
        $this->patchJson('/api/v1/admin/site-appearance', ['header' => $header])->assertOk()->assertJsonPath('data.header.menu_note', $header['menu_note']);
        unset($header['menu_note']);
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['header' => $header])->assertOk()->assertJsonPath('data.header.menu_note', str_repeat('я', 1000));
        }
    }

    #[DataProvider('invalidNotes')]
    public function test_invalid_notes_are_rejected_without_changing_storage(mixed $note): void
    {
        $this->actingAs($this->manager());
        $home = Page::query()->where('slug', 'home')->sole();
        $before = $home->site_layout;
        $header = $this->header();
        $header['menu_note'] = $note;
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['header' => $header])->assertUnprocessable()->assertJsonValidationErrors('header.menu_note', 'error.details');
        }
        self::assertSame($before, $home->refresh()->site_layout);
    }

    public function test_note_changes_require_content_manage_and_remain_global_appearance_only(): void
    {
        $this->actingAs($this->manager());
        $header = $this->header();
        $header['menu_note'] = 'Контакты и помощь';
        $home = Page::query()->where('slug', 'home')->sole();
        $this->patchJson('/api/v1/admin/pages/'.$home->id, ['site_layout' => ['header' => $header]])->assertUnprocessable();
        $this->actingAs($this->manager('analyst'));
        foreach (array_column(self::writePaths(), 0) as $path) {
            $this->patchJson($path, ['header' => $header])->assertForbidden();
        }
    }

    /** @return array<string, array{mixed}> */
    public static function invalidNotes(): array
    {
        return ['too long' => [str_repeat('я', 1001)], 'array' => [['text']], 'number' => [123], 'boolean' => [true]];
    }

    /** @return array<string, array{string}> */
    public static function writePaths(): array
    {
        return ['appearance' => ['/api/v1/admin/site-appearance'], 'legacy' => ['/api/v1/admin/home-page']];
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
