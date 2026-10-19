<?php

namespace App\Services;

use App\Models\HomePage;
use App\Models\Media;
use App\Models\User;
use App\Queries\MediaUsageQuery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MediaManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly StorageCleanupService $storageCleanupService,
        private readonly MediaThumbnailService $thumbnailService,
        private readonly MediaUsageQuery $usageQuery,
    ) {}

    /** @param array{kind: string, title: string, alt?: string|null} $attributes */
    public function create(User $actor, UploadedFile $file, array $attributes): Media
    {
        $path = $file->storePublicly('media', 'public');
        if (! is_string($path)) {
            throw new RuntimeException('Unable to store media.');
        }

        $thumbnail = null;
        try {
            if ($attributes['kind'] === 'image') {
                $thumbnail = $this->thumbnailService->create($file, $path);
            }

            return DB::transaction(function () use ($actor, $file, $attributes, $path, $thumbnail): Media {
                $media = Media::query()->create([
                    'kind' => $attributes['kind'],
                    'disk' => 'public',
                    'path' => $path,
                    'thumbnail_path' => $thumbnail['path'] ?? null,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'title' => $attributes['title'],
                    'alt' => $attributes['kind'] === 'image' ? ($attributes['alt'] ?? null) : null,
                    'width' => $thumbnail['width'] ?? null,
                    'height' => $thumbnail['height'] ?? null,
                ]);
                $this->auditLogService->record($actor, 'media.created', $media);

                return $media;
            });
        } catch (\Throwable $exception) {
            $this->storageCleanupService->schedule('public', $path);
            if ($thumbnail !== null) {
                $this->storageCleanupService->schedule('public', $thumbnail['path']);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Media $media, array $attributes): Media
    {
        return DB::transaction(function () use ($actor, $media, $attributes): Media {
            $media->fill($attributes)->save();
            $this->auditLogService->record($actor, 'media.updated', $media);

            return $media;
        });
    }

    public function delete(User $actor, Media $media): void
    {
        DB::transaction(function () use ($actor, $media): void {
            $homePage = HomePage::query()->lockForUpdate()->find(1);
            $media = Media::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            if ($this->usageQuery->isReferenced($media, $homePage)) {
                throw ValidationException::withMessages(['media' => 'Файл используется. Сначала удалите все ссылки на него.']);
            }
            $this->removeMedia($actor, $media);
        });
    }

    public function deleteIfUnused(User $actor, int $mediaId): bool
    {
        return DB::transaction(function () use ($actor, $mediaId): bool {
            // Same lock order as explicit deletion and content publication.
            $homePage = HomePage::query()->lockForUpdate()->find(1);
            $media = Media::query()->whereKey($mediaId)->lockForUpdate()->first();
            if ($media === null || ! Gate::forUser($actor)->allows('delete', $media)
                || $this->usageQuery->isReferenced($media, $homePage)) {
                return false;
            }
            $this->removeMedia($actor, $media);

            return true;
        });
    }

    private function removeMedia(User $actor, Media $media): void
    {
        $this->auditLogService->record($actor, 'media.deleted', $media);
        $this->storageCleanupService->schedule($media->disk, $media->path);
        if ($media->thumbnail_path !== null) {
            $this->storageCleanupService->schedule($media->disk, $media->thumbnail_path);
        }
        $media->delete();
    }
}
