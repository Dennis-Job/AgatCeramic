<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsRetentionBatches;
use App\Services\Retention\ContactRequestRetentionService;
use App\Services\Retention\RetentionEvidenceService;
use Illuminate\Console\Command;

final class RunContactRetentionCommand extends Command
{
    use RunsRetentionBatches;

    protected $signature = 'retention:contacts {--apply : Apply irreversible retention actions}';

    protected $description = 'Report or apply bounded personal-data retention for contact requests';

    public function handle(ContactRequestRetentionService $service, RetentionEvidenceService $evidence): int
    {
        $dryRun = ! (bool) $this->option('apply');

        return $this->runRetentionBatches('contacts', 'run', $dryRun, fn (): array => $service->run($dryRun), $evidence);
    }
}
