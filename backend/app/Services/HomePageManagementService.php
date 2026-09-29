<?php

namespace App\Services;

use App\Models\HomePage;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomePageManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

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
            $this->auditLogService->record($actor, 'home-page.updated', $page, ['fields' => array_keys($attributes), 'columns' => $changed]);

            return $page;
        });
    }
}
