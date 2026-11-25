<?php

namespace App\Services;

use App\Jobs\DeleteStoredFile;
use App\Models\StorageCleanupTask;
use DateTimeInterface;
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

    public function reserve(string $disk, string $path, DateTimeInterface $expiresAt): int
    {
        return StorageCleanupTask::query()->create([
            'disk' => $disk,
            'path' => $path,
            'next_attempt_at' => $expiresAt,
        ])->id;
    }

    /**
     * Cancel in the same transaction that publishes the file references.
     *
     * @param  list<int>  $ids
     */
    public function cancelReservations(array $ids): bool
    {
        $canceled = StorageCleanupTask::query()->whereIntegerInRaw('id', $ids)
            ->where('status', 'pending')
            ->whereNull('dispatched_at')
            ->where('next_attempt_at', '>', now())
            ->delete();

        return $canceled === count($ids);
    }

    /** @param list<int> $ids */
    public function releaseReservations(array $ids): void
    {
        // A commit may have succeeded even if it raised an exception locally. Its
        // canceled reservations are absent, so recovery can only remove unpublished files.
        $pending = StorageCleanupTask::query()->whereIntegerInRaw('id', $ids)
            ->where('status', 'pending')->whereNull('dispatched_at');
        $pending->update(['next_attempt_at' => now()]);
        $pending->eachById(fn (StorageCleanupTask $task) => $this->dispatchSafely($task));
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
