<?php

namespace App\Queries;

use App\Models\Banner;
use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Slider;
use App\Support\PageBlocks;
use Illuminate\Support\Facades\Storage;

class HomePageContentQuery
{
    public function __construct(private readonly PublishedPageQuery $pages) {}

    /** @return array<string, mixed> */
    public function get(bool $admin = false): array
    {
        if (! $admin) {
            return $this->published();
        }
        $page = HomePage::query()->findOrFail(1);
        $content = $page->content;
        $media = Media::query()->whereIn('id', $page->referencedMediaIds())->get()->keyBy('id');

        foreach ($content['categories']['items'] as $index => $item) {
            $content['categories']['items'][$index]['image_url'] = $this->imageUrl($item['image_url'], $media->get($item['image_media_id']), $admin);
        }
        $content['about']['image_url'] = $this->imageUrl($content['about']['image_url'], $media->get($content['about']['image_media_id']), $admin);
        $content['seo']['og_image_url'] = $this->imageUrl($content['seo']['og_image_url'], $media->get($content['seo']['og_image_media_id']), $admin);
        $content['header']['logo_url'] = $this->imageUrl(null, $media->get($content['header']['logo_media_id']), false);

        $result = [
            'hero_slider_id' => $page->hero_slider_id,
            'hero_slides' => $this->heroSlides($page->hero_slider_id),
            ...$content,
        ];
        $draft = Page::query()->where('slug', 'home')->firstOrFail();

        return [...$result, 'blocks' => $draft->blocks, 'page_id' => $draft->id,
            'is_published' => $draft->is_published, 'published_at' => $draft->published_at?->toAtomString(),
            'has_unpublished_changes' => $draft->published_snapshot !== PageBlocks::snapshot($draft)];
    }

    /** @return array<string, mixed> */
    private function published(): array
    {
        $published = $this->pages->get('home');
        /** @var array<string, mixed> $result */
        $result = config('home_page.content');
        /** @var array{header: array<string, mixed>, footer: array<string, mixed>} $layout */
        $layout = $published['site_layout'];
        $result['header'] = $layout['header'];
        $result['header']['logo_url'] ??= null;
        $result['footer'] = $layout['footer'];
        // Legacy fixed sections remain available to older clients. Missing blocks use
        // static defaults, never values from the editable HomePage draft.
        foreach (PageBlocks::HOME_TYPES as $type) {
            if ($type !== 'hero') {
                $result[$type] = config('home_page.content.'.$type);
            }
        }
        $result['hero_slider_id'] = null;
        $result['hero_slides'] = [];
        $result['blocks'] = $published['blocks'];
        $result['seo'] = $published['seo'];
        /** @var list<array{id: string, type: string, enabled: bool, data: array<string, mixed>}> $blocks */
        $blocks = $published['blocks'];
        foreach ($blocks as $block) {
            if ($block['type'] === 'hero') {
                $result['hero_slider_id'] = $block['data']['slider_id'];
                $result['hero_slides'] = $block['data']['slides'];
            } elseif (array_key_exists($block['type'], $result)) {
                $result[$block['type']] = $block['data'];
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function heroSlides(?int $sliderId): array
    {
        if ($sliderId === null) {
            return [];
        }
        $slider = Slider::query()->whereKey($sliderId)->where('is_published', true)->first();
        if ($slider === null) {
            return [];
        }

        return array_values($slider->banners()->with('image')->where('banners.is_published', true)->get()
            ->map(function (Banner $banner): array {
                $image = $banner->getRelation('image');

                return [
                    'id' => $banner->id,
                    'eyebrow' => $banner->eyebrow ?? '',
                    'title' => $banner->title,
                    'description' => $banner->description ?? '',
                    'image_url' => $this->imageUrl($banner->image_url, $image instanceof Media ? $image : null, false),
                    'image_alt' => $banner->image_alt ?? ($image instanceof Media ? $image->alt ?? '' : ''),
                    'link_label' => $banner->link_label,
                    'link_url' => $banner->link_url,
                ];
            })->all());
    }

    private function imageUrl(?string $fallback, ?Media $media, bool $admin): ?string
    {
        if ($admin || $media?->disk !== 'public') {
            return $fallback;
        }

        return Storage::disk('public')->url($media->path);
    }
}
