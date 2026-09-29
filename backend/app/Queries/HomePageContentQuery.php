<?php

namespace App\Queries;

use App\Models\Banner;
use App\Models\HomePage;
use App\Models\Media;
use App\Models\Slider;
use Illuminate\Support\Facades\Storage;

class HomePageContentQuery
{
    /** @return array<string, mixed> */
    public function get(bool $admin = false): array
    {
        $page = HomePage::query()->findOrFail(1);
        $content = $page->content;
        $media = Media::query()->whereIn('id', $page->referencedMediaIds())->get()->keyBy('id');

        foreach ($content['categories']['items'] as $index => $item) {
            $content['categories']['items'][$index]['image_url'] = $this->imageUrl($item['image_url'], $media->get($item['image_media_id']), $admin);
        }
        $content['about']['image_url'] = $this->imageUrl($content['about']['image_url'], $media->get($content['about']['image_media_id']), $admin);
        $content['seo']['og_image_url'] = $this->imageUrl($content['seo']['og_image_url'], $media->get($content['seo']['og_image_media_id']), $admin);
        $content['header']['logo_url'] = $this->imageUrl(null, $media->get($content['header']['logo_media_id']), false);

        return [
            'hero_slider_id' => $page->hero_slider_id,
            'hero_slides' => $this->heroSlides($page->hero_slider_id),
            ...$content,
        ];
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
