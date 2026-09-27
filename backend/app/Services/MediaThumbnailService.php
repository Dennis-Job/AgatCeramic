<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MediaThumbnailService
{
    /** @return array{path: string, width: int, height: int} */
    public function create(UploadedFile $file, string $originalPath): array
    {
        $realPath = $file->getRealPath();
        $dimensions = is_string($realPath) ? @getimagesize($realPath) : false;
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 10000 || $dimensions[1] > 10000
            || $dimensions[0] * $dimensions[1] > 40000000) {
            throw ValidationException::withMessages(['file' => 'Неподдерживаемый размер изображения.']);
        }
        $bytes = file_get_contents($realPath);
        $source = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['file' => 'Изображение повреждено.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 320 / $width, 320 / $height);
        $thumbWidth = max(1, (int) round($width * $scale));
        $thumbHeight = max(1, (int) round($height * $scale));
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        if (! $thumbnail instanceof GdImage) {
            imagedestroy($source);
            throw new RuntimeException('Unable to create media thumbnail.');
        }
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        $encoded = imagewebp($thumbnail, null, 80);
        $content = ob_get_clean();
        imagedestroy($thumbnail);
        if (! $encoded || ! is_string($content)) {
            throw new RuntimeException('Unable to encode media thumbnail.');
        }

        $path = 'media/thumbnails/'.pathinfo($originalPath, PATHINFO_FILENAME).'.webp';
        if (! Storage::disk('public')->put($path, $content)) {
            throw new RuntimeException('Unable to store media thumbnail.');
        }

        return ['path' => $path, 'width' => $width, 'height' => $height];
    }
}
