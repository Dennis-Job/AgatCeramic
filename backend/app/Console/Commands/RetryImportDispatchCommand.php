<?php

namespace App\Console\Commands;

use App\Models\ImportDispatchTask;
use App\Services\ImportDispatchService;
use Illuminate\Console\Command;

class RetryImportDispatchCommand extends Command
{
    protected $signature = 'imports:retry-dispatch {--limit=100 : Maximum number of eligible imports to dispatch}';

    protected $description = 'Dispatch pending or stale durable import processing tasks';

    public function handle(ImportDispatchService $dispatchService): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $tasks = ImportDispatchTask::query()
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('status', 'pending')
                        ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
                })->orWhere(function ($query): void {
                    $query->where('status', 'dispatched')
                        ->where('dispatched_at', '<=', now()->subMinutes(10));
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($tasks as $task) {
            $dispatchService->dispatchSafely($task);
        }

        $this->info("Dispatched {$tasks->count()} import task(s).");

        return self::SUCCESS;
    }
}
