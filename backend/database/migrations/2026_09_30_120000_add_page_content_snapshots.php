<?php

use App\Support\PageBlocks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->json('blocks')->nullable();
            $table->json('seo')->nullable();
            $table->json('published_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_slug')->nullable()->unique();
            $table->json('site_layout')->nullable();
        });
        Schema::create('page_media', function (Blueprint $table): void {
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->primary(['page_id', 'media_id']);
        });
        DB::table('pages')->orderBy('id')->each(function (object $page): void {
            /** @var object{id: int, title: string, slug: string, body: string, is_published: bool, updated_at: string|null} $page */
            $blocks = [['id' => 'body', 'type' => 'text', 'enabled' => true, 'data' => ['title' => $page->title, 'body' => $page->body]]];
            $seo = PageBlocks::defaultSeo($page->title);
            $snapshot = ['title' => $page->title, 'slug' => $page->slug, 'body' => $page->body, 'blocks' => $blocks, 'seo' => $seo];
            DB::table('pages')->where('id', $page->id)->update([
                'blocks' => $this->json($blocks), 'seo' => $this->json($seo),
                'published_snapshot' => $page->is_published ? $this->json($snapshot) : null,
                'published_at' => $page->is_published ? $page->updated_at : null,
                'published_slug' => $page->is_published ? $page->slug : null,
            ]);
        });
        $this->seedPages();
        DB::table('pages')->orderBy('id')->each(function (object $page): void {
            /** @var object{id: int, blocks: string, seo: string, site_layout: string|null} $page */
            $ids = PageBlocks::mediaIds($this->decode($page->blocks));
            $ids = [...$ids, ...PageBlocks::mediaIds($this->decode($page->seo))];
            $ids = [...$ids, ...PageBlocks::mediaIds($page->site_layout === null ? [] : $this->decode($page->site_layout))];
            foreach (array_unique($ids) as $id) {
                if (DB::table('media')->where('id', $id)->exists()) {
                    DB::table('page_media')->insert(['page_id' => $page->id, 'media_id' => $id]);
                }
            }
        });
    }

    private function seedPages(): void
    {
        $home = DB::table('home_pages')->where('id', 1)->first();
        /** @var object{content: string, hero_slider_id: int|null}|null $home */
        /** @var array{about?: array{description: string}, seo?: array<string, mixed>, header?: array{navigation: list<array{label: string, to: string}>}, footer?: array<string, mixed>} $content */
        $content = $home === null ? [] : $this->decode($home->content);
        if ($home !== null && isset($content['header'])) {
            $navigation = $content['header']['navigation'];
            foreach (['/catalog' => 'Каталог', '/about' => 'О нас', '/contacts' => 'Контакты'] as $to => $label) {
                if (! in_array($to, array_column($navigation, 'to'), true)) {
                    $navigation[] = ['label' => $label, 'to' => $to];
                }
            }
            $content['header']['navigation'] = $navigation;
            DB::table('home_pages')->where('id', 1)->update(['content' => $this->json($content)]);
        }
        $titles = ['home' => 'Главная', 'contacts' => 'Контакты', 'about' => 'О нас', 'catalog' => 'Каталог'];
        foreach ($titles as $slug => $title) {
            // Existing pages own their slugs and retain all content and publication state.
            if (DB::table('pages')->where('slug', $slug)->exists()) {
                if ($slug === 'home') {
                    $this->mergeExistingHome($content, $home?->hero_slider_id);
                }

                continue;
            }
            $blocks = match ($slug) {
                'home' => PageBlocks::home($content, $home?->hero_slider_id),
                'contacts' => [['id' => 'stores', 'type' => 'stores', 'enabled' => true, 'data' => ['title' => 'Наши магазины']]],
                'about' => [['id' => 'about', 'type' => 'text', 'enabled' => true, 'data' => ['title' => 'О нас', 'body' => $content['about']['description'] ?? '']]],
                'catalog' => [['id' => 'catalog', 'type' => 'catalog', 'enabled' => true, 'data' => ['title' => 'Каталог', 'description' => 'Керамическая плитка, керамогранит и мозаика.']]],
            };
            $seo = $slug === 'home' ? ($content['seo'] ?? PageBlocks::defaultSeo($title)) : PageBlocks::defaultSeo($title.' — AgatCeramic');
            $snapshot = ['title' => $title, 'slug' => $slug, 'body' => '', 'blocks' => $blocks, 'seo' => $seo];
            $layout = $slug === 'home' ? ['header' => $content['header'] ?? [], 'footer' => $content['footer'] ?? []] : null;
            if ($layout !== null) {
                $snapshot['site_layout'] = $layout;
            }
            DB::table('pages')->insert([
                'title' => $title, 'slug' => $slug, 'body' => '', 'blocks' => $this->json($blocks), 'seo' => $this->json($seo),
                'is_published' => true, 'published_snapshot' => $this->json($snapshot),
                'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                'published_slug' => $slug,
                'site_layout' => $layout === null ? null : $this->json($layout),
            ]);
        }
    }

    /** @param array<string, mixed> $content */
    private function mergeExistingHome(array $content, ?int $sliderId): void
    {
        $existing = DB::table('pages')->where('slug', 'home')->firstOrFail();
        /** @var object{id: int, title: string, body: string, is_published: bool, blocks: string} $existing */
        $homeBlocks = PageBlocks::home($content, $sliderId);
        $legacyBlocks = $this->decode($existing->blocks);
        $draftBlocks = [...$homeBlocks, ...$legacyBlocks];
        $seo = $content['seo'] ?? PageBlocks::defaultSeo($existing->title);
        $snapshot = ['title' => $existing->title, 'slug' => 'home', 'body' => $existing->is_published ? $existing->body : '',
            'blocks' => $existing->is_published ? $draftBlocks : $homeBlocks, 'seo' => $seo,
            'site_layout' => ['header' => $content['header'] ?? [], 'footer' => $content['footer'] ?? []]];
        DB::table('pages')->where('id', $existing->id)->update([
            'blocks' => $this->json($draftBlocks), 'seo' => $this->json(is_array($seo) ? $seo : []),
            'is_published' => true, 'published_snapshot' => $this->json($snapshot), 'published_at' => now(), 'published_slug' => 'home',
            'site_layout' => $this->json($snapshot['site_layout']),
        ]);
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<mixed> */
    private function decode(string $value): array
    {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new LogicException('Stored page content must be an object or array.');
        }

        return $decoded;
    }

    public function down(): void
    {
        // Older application versions tolerate these additive columns. A database backup
        // is required to restore the previous schema without losing published snapshots.
        throw new RuntimeException('Page snapshots contain independent draft/published data. Restore a verified pre-migration backup to downgrade the schema; application rollback may leave the additive columns in place.');
    }
};
