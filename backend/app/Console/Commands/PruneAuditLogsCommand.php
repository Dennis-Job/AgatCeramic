<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Permanently remove audit records older than the configured retention period';

    public function handle(): int
    {
        $cutoff = now()->subYears((int) config('audit.retention_years'));
        $deleted = AuditLog::query()->where('occurred_at', '<', $cutoff)->delete();

        $this->components->info("Pruned {$deleted} audit log record(s).");

        return self::SUCCESS;
    }
}
