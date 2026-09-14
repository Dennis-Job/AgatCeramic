<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsRetentionBatches;
use App\Services\Retention\RetentionEvidenceService;
use App\Services\Retention\RetentionPolicy;
use App\Services\Retention\RetentionTombstoneRecord;
use App\Services\Retention\TombstoneReplayService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use SplFileObject;

final class ReplayRetentionTombstonesCommand extends Command
{
    use RunsRetentionBatches;

    protected $signature = 'retention:tombstones-replay {path : PII-free JSONL tombstone journal} {--apply : Apply irreversible replay actions}';

    protected $description = 'Dry-run or replay a bounded tombstone journal after restore';

    public function handle(
        RetentionPolicy $policy,
        TombstoneReplayService $service,
        RetentionEvidenceService $evidence,
    ): int {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('Tombstone journal is not a readable file.');
        }

        $records = $this->readRecords($path, $policy->batchSize());
        $dryRun = ! (bool) $this->option('apply');

        return $this->runRetentionBatches(
            'restore',
            'tombstone-replay',
            $dryRun,
            fn (): array => [$service->replay($records, $dryRun)],
            $evidence,
        );
    }

    /** @return list<RetentionTombstoneRecord> */
    private function readRecords(string $path, int $limit): array
    {
        $file = new SplFileObject($path, 'r');
        $records = [];

        while (! $file->eof() && count($records) < $limit) {
            $line = trim((string) $file->fgets());
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Tombstone journal row must be a JSON object.');
            }

            $typed = [];
            foreach ($decoded as $key => $value) {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('Tombstone journal keys must be strings.');
                }
                $typed[$key] = $value;
            }

            $records[] = RetentionTombstoneRecord::fromArray($typed);
        }

        return $records;
    }
}
