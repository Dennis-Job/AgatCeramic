<?php

namespace App\Jobs;

use App\Models\StorageCleanupTask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

class DeleteStoredFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $cleanupTaskId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        $delays = config('queue.job_backoff.storage_cleanup', [60, 300]);
        if (! is_array($delays) || $delays === []) {
            throw new LogicException('Storage cleanup queue backoff must be a non-empty list of integers.');
        }

        $validated = [];
        foreach ($delays as $delay) {
            if (! is_int($delay) || $delay < 0) {
                throw new LogicException('Storage cleanup queue backoff must contain non-negative integers.');
            }
            $validated[] = $delay;
        }

        return $validated;
    }

    public function handle(): void
    {
        $task = StorageCleanupTask::query()->find($this->cleanupTaskId);

        if ($task === null || $task->status === 'completed') {
            return;
        }

        $task->forceFill([
            'status' => 'processing',
            'attempts' => $task->attempts + 1,
            'last_attempted_at' => now(),
            'last_error' => null,
        ])->save();

        try {
            if (! Storage::disk($task->disk)->delete($task->path)) {
                throw new RuntimeException("Storage disk [{$task->disk}] did not delete [{$task->path}].");
            }
        } catch (Throwable $exception) {
            $task->forceFill([
                'status' => 'pending',
                'last_error' => mb_substr($exception->getMessage(), 0, 65535),
                'next_attempt_at' => now()->addMinutes(5),
            ])->save();

            throw $exception;
        }

        $task->forceFill([
            'status' => 'completed',
            'last_error' => null,
            'next_attempt_at' => null,
            'completed_at' => now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        StorageCleanupTask::query()->whereKey($this->cleanupTaskId)->where('status', '!=', 'completed')->update([
            'status' => 'failed',
            'last_error' => $exception === null ? 'Queue job failed.' : mb_substr($exception->getMessage(), 0, 65535),
            'next_attempt_at' => now()->addMinutes(15),
            'updated_at' => now(),
        ]);
    }
}
