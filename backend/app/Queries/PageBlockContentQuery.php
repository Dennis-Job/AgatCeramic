<?php

namespace App\Queries;

use App\Models\Banner;
use App\Models\Media;
use App\Models\Slider;
use App\Support\PageBlocks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class PageBlockContentQuery
{
    /** @param list<array{id: string, type: string, enabled: bool, data: array<string, mixed>}> $blocks
     * @return list<array{id: string, type: string, enabled: bool, data: array<string, mixed>}>
     */
    public function resolve(array $blocks): array
    {
        $blocks = array_values(array_filter($blocks, static fn (array $block): bool => $block['enabled']));
        $media = Media::query()->whereIn('id', PageBlocks::mediaIds($blocks))->get()->keyBy('id');
        foreach ($blocks as &$block) {
            if ($block['type'] === 'hero') {
                $id = $block['data']['slider_id'] ?? null;
                $block['data']['slides'] = $this->heroSlides(is_int($id) ? $id : null);
            }
            $block['data'] = $this->resolveImages($block['data'], $media);
        }

        return $blocks;
    }

    /** @param array<string, mixed> $data
     * @param  Collection<int|string, Media>  $media
     * @return array<string, mixed>
     */
    private function resolveImages(array $data, Collection $media): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $data[$key] = $this->resolveImages($value, $media);
            } elseif (str_ends_with((string) $key, '_media_id') && is_int($value)) {
                $image = $media->get($value);
                if ($image?->disk === 'public') {
                    $urlKey = substr((string) $key, 0, -strlen('_media_id')).'_url';
                    $data[$urlKey] = Storage::disk('public')->url($image->path);
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    public function seo(array $seo): array
    {
        return $this->resolveImages($seo, Media::query()->whereIn('id', PageBlocks::mediaIds($seo))->get()->keyBy('id'));
    }

    /** @return list<array<string, mixed>> */
    public function heroSlides(?int $sliderId): array
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
                    'id' => $banner->id, 'eyebrow' => $banner->eyebrow ?? '', 'title' => $banner->title,
                    'description' => $banner->description ?? '',
                    'image_url' => $image instanceof Media && $image->disk === 'public' ? Storage::disk('public')->url($image->path) : $banner->image_url,
                    'image_alt' => $banner->image_alt ?? ($image instanceof Media ? $image->alt ?? '' : ''),
                    'link_label' => $banner->link_label, 'link_url' => $banner->link_url,
                ];
            })->all());
    }
}
