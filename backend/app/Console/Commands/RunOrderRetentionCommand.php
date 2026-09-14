<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsRetentionBatches;
use App\Services\Retention\OrderRetentionService;
use App\Services\Retention\RetentionEvidenceService;
use Illuminate\Console\Command;

final class RunOrderRetentionCommand extends Command
{
    use RunsRetentionBatches;

    protected $signature = 'retention:orders {--apply : Apply irreversible retention actions}';

    protected $description = 'Report or apply bounded personal-data retention for orders';

    public function handle(OrderRetentionService $service, RetentionEvidenceService $evidence): int
    {
        $dryRun = ! (bool) $this->option('apply');

        return $this->runRetentionBatches('orders', 'run', $dryRun, fn (): array => $service->run($dryRun), $evidence);
    }
}
