<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BannerManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Banner
    {
        $this->validateLink($attributes);

        return DB::transaction(function () use ($actor, $attributes): Banner {
            $banner = Banner::query()->create($attributes);
            $this->auditLogService->record($actor, 'banner.created', $banner);

            return $banner->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Banner $banner, array $attributes): Banner
    {
        $this->validateLink(array_merge($banner->only(['link_label', 'link_url']), $attributes));

        return DB::transaction(function () use ($actor, $banner, $attributes): Banner {
            $banner->fill($attributes)->save();
            $this->auditLogService->record($actor, 'banner.updated', $banner);

            return $banner;
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
