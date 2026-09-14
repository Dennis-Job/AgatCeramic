<?php

namespace App\Services\Retention;

use Carbon\CarbonImmutable;

final readonly class RetentionBatchResult
{
    public function __construct(
        public string $batchId,
        public string $scope,
        public string $action,
        public bool $dryRun,
        public ?CarbonImmutable $cutoff,
        public int $eligible,
        public int $processed,
        public int $held,
        public int $exceptions,
        public int $errors = 0,
    ) {}

    /** @return array<string, int|string|null> */
    public function summary(): array
    {
        return [
            'batch_id' => $this->batchId,
            'scope' => $this->scope,
            'action' => $this->action,
            'mode' => $this->dryRun ? 'dry-run' : 'apply',
            'cutoff' => $this->cutoff?->toIso8601String(),
            'eligible' => $this->eligible,
            'processed' => $this->processed,
            'holds' => $this->held,
            'exceptions' => $this->exceptions,
            'errors' => $this->errors,
        ];
    }
}
