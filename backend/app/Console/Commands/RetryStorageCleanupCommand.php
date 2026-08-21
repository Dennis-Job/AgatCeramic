<?php

namespace App\Console\Commands;

use App\Models\StorageCleanupTask;
use App\Services\StorageCleanupService;
use Illuminate\Console\Command;

class RetryStorageCleanupCommand extends Command
{
    protected $signature = 'storage-cleanup:retry {--limit=100 : Maximum number of eligible tasks to dispatch}';

    protected $description = 'Dispatch pending or failed durable storage cleanup tasks';

    public function handle(StorageCleanupService $cleanupService): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $tasks = StorageCleanupTask::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('dispatched_at')->orWhere('dispatched_at', '<=', now()->subMinutes(10)))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($tasks as $task) {
            $cleanupService->dispatchSafely($task);
        }

        $this->info("Dispatched {$tasks->count()} storage cleanup task(s).");
        $this->table(['status', 'count'], StorageCleanupTask::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (StorageCleanupTask $task): array => [$task->status, $task->getAttribute('aggregate')])
            ->all());

        return self::SUCCESS;
    }
}
