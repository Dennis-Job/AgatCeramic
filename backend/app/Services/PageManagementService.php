<?php

namespace App\Services;

use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Support\PageBlocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PageManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Page
    {
        return DB::transaction(function () use ($actor, $attributes): Page {
            if (! is_string($attributes['title'] ?? null)) {
                throw new \LogicException('Validated page title must be a string.');
            }
            $attributes['body'] ??= '';
            $attributes['blocks'] ??= [['id' => 'body', 'type' => 'text', 'enabled' => true, 'data' => ['title' => $attributes['title'], 'body' => $attributes['body']]]];
            $attributes['seo'] ??= PageBlocks::defaultSeo($attributes['title']);
            $page = Page::query()->create($attributes);
            $this->syncMedia($page);
            $this->auditLogService->record($actor, 'page.created', $page);

            return $page->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Page $page, array $attributes): Page
    {
        return DB::transaction(function () use ($actor, $page, $attributes): Page {
            // Same lock order as the legacy home editor and media removal.
            $home = $page->slug === 'home' ? HomePage::query()->lockForUpdate()->find(1) : null;
            $page = Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            if (isset($attributes['slug']) && $attributes['slug'] !== $page->slug && in_array($page->slug, PageBlocks::RESERVED_SLUGS, true)) {
                throw ValidationException::withMessages(['slug' => 'Адрес системной страницы нельзя изменить.']);
            }
            if (isset($attributes['body']) && ! isset($attributes['blocks'])) {
                $blocks = $page->blocks;
                foreach ($blocks as &$block) {
                    if ($block['type'] === 'text' && $block['id'] === 'body') {
                        $block['data']['body'] = $attributes['body'];
                        $block['data']['title'] = $attributes['title'] ?? $page->title;
                    }
                }
                unset($block);
                if ($attributes['body'] !== '' && ! in_array('body', array_column($blocks, 'id'), true)) {
                    $blocks[] = ['id' => 'body', 'type' => 'text', 'enabled' => true, 'data' => ['title' => $attributes['title'] ?? $page->title, 'body' => $attributes['body']]];
                }
                $attributes['blocks'] = $blocks;
            }
            $page->fill($attributes)->save();
            $this->syncMedia($page);
            if ($home !== null) {
                $this->syncHomeDraft($page, $home);
            }
            $this->auditLogService->record($actor, 'page.updated', $page);

            return $page;
        });
    }

    public function delete(User $actor, Page $page): void
    {
        DB::transaction(function () use ($actor, $page): void {
            if (in_array($page->slug, PageBlocks::RESERVED_SLUGS, true)) {
                throw ValidationException::withMessages(['page' => 'Системную страницу нельзя удалить. Её можно снять с публикации.']);
            }
            $this->auditLogService->record($actor, 'page.deleted', $page);
            $page->delete();
        });
    }

    public function publish(User $actor, Page $page): Page
    {
        return DB::transaction(function () use ($actor, $page): Page {
            if ($page->slug === 'home') {
                HomePage::query()->lockForUpdate()->findOrFail(1);
            }
            $page = Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $page->published_snapshot = $this->snapshot($page);
            $page->is_published = true;
            $page->published_slug = $page->slug;
            $page->published_at = now();
            $this->syncMedia($page);
            $page->save();
            $this->auditLogService->record($actor, 'page.published', $page);

            return $page;
        });
    }

    /** @return array<string, mixed> */
    public function snapshot(Page $page): array
    {
        return PageBlocks::snapshot($page);
    }

    public function syncMedia(Page $page): void
    {
        $ids = PageBlocks::mediaIds([$page->blocks, $page->seo, $page->site_layout ?? [], $page->published_snapshot ?? []]);
        $found = $ids === [] ? [] : Media::query()->whereIn('id', $ids)->where('kind', 'image')->where('disk', 'public')->lockForUpdate()->pluck('id')->all();
        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages(['blocks' => 'Изображение недоступно. Выберите публичный файл из медиатеки.']);
        }
        DB::table('page_media')->where('page_id', $page->id)->delete();
        foreach ($ids as $id) {
            DB::table('page_media')->insert(['page_id' => $page->id, 'media_id' => $id]);
        }
    }

    private function syncHomeDraft(Page $page, HomePage $home): void
    {
        $content = $home->content;
        foreach ($page->blocks as $block) {
            if ($block['type'] === 'hero') {
                $home->setAttribute('hero_slider_id', $block['data']['slider_id']);
            } elseif (in_array($block['type'], PageBlocks::HOME_TYPES, true)) {
                $content[$block['type']] = $block['data'];
            }
        }
        $content['seo'] = $page->seo;
        $home->setAttribute('content', $content);
        $home->save();
    }
}
