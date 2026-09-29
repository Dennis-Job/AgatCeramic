<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $hero_slider_id
 * @property array{
 *     header: array{logo_media_id: int|null},
 *     categories: array{items: list<array{image_media_id: int|null, image_url: string|null}>},
 *     about: array{image_media_id: int|null, image_url: string|null},
 *     seo: array{og_image_media_id: int|null, og_image_url: string|null}
 * } $content
 */
#[Fillable(['hero_slider_id', 'content'])]
class HomePage extends Model
{
    public $incrementing = false;

    /** @return list<int> */
    public function referencedMediaIds(): array
    {
        $content = $this->content;
        $ids = [$content['header']['logo_media_id'], $content['about']['image_media_id'], $content['seo']['og_image_media_id']];
        foreach ($content['categories']['items'] as $item) {
            $ids[] = $item['image_media_id'];
        }

        return array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));
    }

    /** @return BelongsTo<Slider, $this> */
    public function heroSlider(): BelongsTo
    {
        return $this->belongsTo(Slider::class, 'hero_slider_id');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
