<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\HomePage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BannerManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Banner
    {
        if (isset($attributes['image_media_id'])) {
            $attributes['image_url'] = null;
        }
        $this->validateLink($attributes);

        return DB::transaction(function () use ($actor, $attributes): Banner {
            // URL-only references share the media cleanup/content lock.
            HomePage::query()->lockForUpdate()->findOrFail(1);
            $banner = Banner::query()->create($attributes);
            $this->auditLogService->record($actor, 'banner.created', $banner);

            return $banner->refresh()->load('image');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Banner $banner, array $attributes): Banner
    {
        if (isset($attributes['image_media_id'])) {
            $attributes['image_url'] = null;
        }
        $this->validateLink(array_merge($banner->only(['link_label', 'link_url']), $attributes));

        return DB::transaction(function () use ($actor, $banner, $attributes): Banner {
            HomePage::query()->lockForUpdate()->findOrFail(1);
            $banner->fill($attributes)->save();
            $this->auditLogService->record($actor, 'banner.updated', $banner);

            return $banner->load('image');
        });
    }

    public function delete(User $actor, Banner $banner): void
    {
        DB::transaction(function () use ($actor, $banner): void {
            $this->auditLogService->record($actor, 'banner.deleted', $banner);
            $banner->delete();
        });
    }

    /** @param array<string, mixed> $attributes */
    private function validateLink(array $attributes): void
    {
        if (filled($attributes['link_label'] ?? null) === filled($attributes['link_url'] ?? null)) {
            return;
        }

        throw ValidationException::withMessages(['link_url' => 'Укажите и ссылку, и подпись кнопки.']);
    }
}
