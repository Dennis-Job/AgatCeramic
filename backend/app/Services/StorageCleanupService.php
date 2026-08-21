<?php

namespace App\Services;

use App\Jobs\DeleteStoredFile;
use App\Models\StorageCleanupTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StorageCleanupService
{
    public function schedule(string $disk, string $path): StorageCleanupTask
    {
        $task = StorageCleanupTask::query()->create([
            'disk' => $disk,
            'path' => $path,
        ]);

        DB::afterCommit(fn () => $this->dispatchSafely($task));

        return $task;
    }

    public function dispatchSafely(StorageCleanupTask $task): void
    {
        try {
            $task->forceFill(['dispatched_at' => now()])->save();
            DeleteStoredFile::dispatch($task->id);
        } catch (Throwable $exception) {
            $task->forceFill([
                'status' => 'pending',
                'last_error' => mb_substr($exception->getMessage(), 0, 65535),
                'next_attempt_at' => now()->addMinute(),
            ])->save();
            Log::error('Unable to dispatch storage cleanup task.', [
                'cleanup_task_id' => $task->id,
                'disk' => $task->disk,
                'exception' => $exception,
            ]);
        }
    }
}
