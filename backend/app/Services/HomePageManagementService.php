<?php

namespace App\Services;

use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomePageManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService, private readonly PageManagementService $pages) {}

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, array $attributes): HomePage
    {
        return DB::transaction(function () use ($actor, $attributes): HomePage {
            $page = HomePage::query()->lockForUpdate()->findOrFail(1);
            $content = $page->content;
            foreach ($attributes as $key => $value) {
                if ($key === 'hero_slider_id') {
                    if (! is_int($value) && $value !== null) {
                        throw new \LogicException('Validated hero_slider_id must be an integer or null.');
                    }
                    $page->hero_slider_id = $value;
                } else {
                    $content = array_replace($content, [$key => $value]);
                }
            }
            $page->setAttribute('content', $content);
            $mediaIds = $page->referencedMediaIds();
            $lockedMediaIds = $mediaIds === [] ? [] : Media::query()->whereIn('id', $mediaIds)->where('kind', 'image')->lockForUpdate()->pluck('id')->all();
            if (count($lockedMediaIds) !== count($mediaIds)) {
                throw ValidationException::withMessages(['image_media_id' => 'Один из выбранных файлов больше недоступен. Обновите страницу и выберите изображение повторно.']);
            }
            $changed = array_keys($page->getDirty());
            $page->save();
            $draft = Page::query()->where('slug', 'home')->lockForUpdate()->firstOrFail();
            $blocks = $draft->blocks;
            foreach ($blocks as &$block) {
                if ($block['type'] === 'hero' && array_key_exists('hero_slider_id', $attributes)) {
                    $block['data'] = ['slider_id' => $page->hero_slider_id];
                } elseif (array_key_exists($block['type'], $attributes)) {
                    $data = $attributes[$block['type']];
                    if (! is_array($data)) {
                        throw new \LogicException('Validated page section must be an object.');
                    }
                    /** @var array<string, mixed> $data */
                    $block['data'] = $data;
                }
            }
            $draft->blocks = $blocks;
            $draft->site_layout = ['header' => $content['header'], 'footer' => $content['footer']];
            if (isset($attributes['seo'])) {
                $draft->setAttribute('seo', $attributes['seo']);
            }
            $draft->save();
            $this->pages->syncMedia($draft);
            $this->auditLogService->record($actor, 'home-page.updated', $page, ['fields' => array_keys($attributes), 'columns' => $changed]);

            return $page;
        });
    }
}
