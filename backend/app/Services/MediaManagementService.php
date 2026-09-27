<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MediaManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly StorageCleanupService $storageCleanupService,
        private readonly MediaThumbnailService $thumbnailService,
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
            $media = Media::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $used = DB::table('categories')->where('image_id', $media->id)->exists()
                || DB::table('brands')->where('logo_id', $media->id)->exists()
                || DB::table('banners')->where('image_media_id', $media->id)->exists()
                || DB::table('brand_media_documents')->where('media_id', $media->id)->exists()
                || DB::table('category_media_documents')->where('media_id', $media->id)->exists();
            if ($used) {
                throw ValidationException::withMessages(['media' => 'Файл используется. Сначала удалите все ссылки на него.']);
            }
            $this->auditLogService->record($actor, 'media.deleted', $media);
            $this->storageCleanupService->schedule($media->disk, $media->path);
            if ($media->thumbnail_path !== null) {
                $this->storageCleanupService->schedule($media->disk, $media->thumbnail_path);
            }
            $media->delete();
        });
    }
}
