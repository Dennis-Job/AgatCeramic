<?php

namespace App\Services;

use App\Models\ProductImageImport;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportSubmissionService
{
    public function __construct(private readonly ImportDispatchService $dispatchService) {}

    public function submitProductWorkbook(
        User $user,
        UploadedFile $file,
        string $directory,
        ?int $categoryId = null,
        ?string $operation = null,
    ): ProductImport {
        $path = $this->storeSource($file, $directory, 'XLSX-файл');

        try {
            /** @var ProductImport $import */
            $import = DB::transaction(function () use ($user, $file, $path, $categoryId, $operation): ProductImport {
                $attributes = [
                    'user_id' => $user->id,
                    'original_filename' => $this->filename($file),
                    'disk' => 'local',
                    'path' => $path,
                    'status' => 'pending',
                ];

                if ($categoryId !== null) {
                    $attributes['category_id'] = $categoryId;
                }

                if ($operation !== null) {
                    $attributes['operation'] = $operation;
                }

                $import = ProductImport::query()->create($attributes);

                $this->dispatchService->scheduleProductImport($import);

                return $import;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return $import;
    }

    public function submitProductImageArchive(User $user, UploadedFile $file): ProductImageImport
    {
        $path = $this->storeSource($file, 'product-image-imports', 'ZIP-архив');

        try {
            /** @var ProductImageImport $import */
            $import = DB::transaction(function () use ($user, $file, $path): ProductImageImport {
                $import = ProductImageImport::query()->create([
                    'user_id' => $user->id,
                    'original_filename' => $this->filename($file),
                    'disk' => 'local',
                    'path' => $path,
                    'status' => 'pending',
                ]);

                $this->dispatchService->scheduleProductImageImport($import);

                return $import;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return $import;
    }

    private function storeSource(UploadedFile $file, string $directory, string $sourceType): string
    {
        $path = $file->store($directory, 'local');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException("Не удалось сохранить {$sourceType} для импорта.");
        }

        return $path;
    }

    private function filename(UploadedFile $file): string
    {
        return mb_substr(basename($file->getClientOriginalName()), 0, 255);
    }
}
