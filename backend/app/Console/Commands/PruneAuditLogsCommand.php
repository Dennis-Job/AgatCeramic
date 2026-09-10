<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Permanently remove audit records older than the configured retention period';

    public function handle(): int
    {
        $retentionYears = config('audit.retention_years');
        $cutoff = now()->subYears(is_int($retentionYears) ? $retentionYears : 0);
        $deleted = DB::transaction(function () use ($cutoff): int {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL app.audit_log_prune = 'on'");
            }

            $deleted = AuditLog::query()->where('occurred_at', '<', $cutoff)->delete();

            return is_int($deleted) ? $deleted : 0;
        });

        $this->components->info("Pruned {$deleted} audit log record(s).");

        return self::SUCCESS;
    }
}
