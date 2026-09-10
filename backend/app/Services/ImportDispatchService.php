<?php

namespace App\Services;

use App\Jobs\ProcessProductImageImport;
use App\Jobs\ProcessProductImport;
use App\Models\ImportDispatchTask;
use App\Models\ProductImageImport;
use App\Models\ProductImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportDispatchService
{
    public function scheduleProductImport(ProductImport $import): ImportDispatchTask
    {
        return $this->schedule(ImportDispatchTask::TYPE_PRODUCT, $import->id);
    }

    public function scheduleProductImageImport(ProductImageImport $import): ImportDispatchTask
    {
        return $this->schedule(ImportDispatchTask::TYPE_PRODUCT_IMAGE, $import->id);
    }

    public function continueProductImport(ProductImport $import): void
    {
        $this->continue(ImportDispatchTask::TYPE_PRODUCT, $import->id);
    }

    public function continueProductImageImport(ProductImageImport $import): void
    {
        $this->continue(ImportDispatchTask::TYPE_PRODUCT_IMAGE, $import->id);
    }

    public function completeProductImport(ProductImport $import): void
    {
        $this->complete(ImportDispatchTask::TYPE_PRODUCT, $import->id);
    }

    public function completeProductImageImport(ProductImageImport $import): void
    {
        $this->complete(ImportDispatchTask::TYPE_PRODUCT_IMAGE, $import->id);
    }

    public function dispatchSafely(ImportDispatchTask $task): void
    {
        try {
            $task->forceFill([
                'status' => 'dispatched',
                'attempts' => $task->attempts + 1,
                'dispatched_at' => now(),
                'next_attempt_at' => null,
                'last_error' => null,
            ])->save();

            match ($task->import_type) {
                ImportDispatchTask::TYPE_PRODUCT => ProcessProductImport::dispatch($task->import_id),
                ImportDispatchTask::TYPE_PRODUCT_IMAGE => ProcessProductImageImport::dispatch($task->import_id),
                default => throw new \RuntimeException('Неизвестный тип задачи импорта.'),
            };
        } catch (Throwable $exception) {
            $task->forceFill([
                'status' => 'pending',
                'last_error' => mb_substr($exception->getMessage(), 0, 65535),
                'next_attempt_at' => now()->addMinute(),
            ])->save();
            Log::error('Unable to dispatch import processing task.', [
                'import_dispatch_task_id' => $task->id,
                'import_type' => $task->import_type,
                'import_id' => $task->import_id,
                'exception' => $exception,
            ]);
        }
    }

    private function schedule(string $type, int $importId): ImportDispatchTask
    {
        /** @var ImportDispatchTask $task */
        $task = ImportDispatchTask::query()->firstOrCreate([
            'import_type' => $type,
            'import_id' => $importId,
        ]);

        DB::afterCommit(fn () => $this->dispatchSafely($task));

        return $task;
    }

    private function continue(string $type, int $importId): void
    {
        $task = ImportDispatchTask::query()
            ->where('import_type', $type)
            ->where('import_id', $importId)
            ->lockForUpdate()
            ->first();
        if ($task === null) {
            $task = ImportDispatchTask::query()->create([
                'import_type' => $type,
                'import_id' => $importId,
            ]);
        }
        $task->forceFill([
            'status' => 'pending',
            'next_attempt_at' => null,
            'last_error' => null,
            'completed_at' => null,
        ])->save();

        DB::afterCommit(fn () => $this->dispatchSafely($task));
    }

    private function complete(string $type, int $importId): void
    {
        ImportDispatchTask::query()
            ->where('import_type', $type)
            ->where('import_id', $importId)
            ->update(['status' => 'completed', 'completed_at' => now(), 'next_attempt_at' => null]);
    }
}
