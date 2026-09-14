<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsRetentionBatches;
use App\Services\Retention\RetentionEvidenceService;
use App\Services\Retention\TechnicalDataRetentionService;
use Illuminate\Console\Command;

final class RunTechnicalRetentionCommand extends Command
{
    use RunsRetentionBatches;

    protected $signature = 'retention:technical {--apply : Apply irreversible retention actions}';

    protected $description = 'Report or apply bounded retention for sessions, reset tokens, and failed jobs';

    public function handle(TechnicalDataRetentionService $service, RetentionEvidenceService $evidence): int
    {
        $dryRun = ! (bool) $this->option('apply');

        return $this->runRetentionBatches('technical', 'run', $dryRun, fn (): array => $service->run($dryRun), $evidence);
    }
}
