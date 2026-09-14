<?php

namespace App\Console\Commands\Concerns;

use App\Services\Retention\RetentionApplyBlocked;
use App\Services\Retention\RetentionBatchResult;
use App\Services\Retention\RetentionEvidenceService;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

trait RunsRetentionBatches
{
    /**
     * @param  Closure(): list<RetentionBatchResult>  $operation
     */
    private function runRetentionBatches(
        string $scope,
        string $action,
        bool $dryRun,
        Closure $operation,
        RetentionEvidenceService $evidence,
    ): int {
        try {
            foreach ($operation() as $result) {
                $this->line(json_encode($result->summary(), JSON_THROW_ON_ERROR));
            }

            return self::SUCCESS;
        } catch (RetentionApplyBlocked $exception) {
            return $this->retentionFailure($evidence, $scope, $action, $dryRun, $exception->reasonCode);
        } catch (Throwable $exception) {
            $batchId = $evidence->recordFailure($scope, $action, $dryRun, 'unexpected_failure');
            Log::error('Retention batch failed.', [
                'scope' => $scope,
                'batch_id' => $batchId,
                'exception_class' => $exception::class,
            ]);
            $this->error(json_encode([
                'batch_id' => $batchId,
                'scope' => $scope,
                'status' => 'failed',
                'failure_code' => 'unexpected_failure',
            ], JSON_THROW_ON_ERROR));

            return self::FAILURE;
        }
    }

    private function retentionFailure(
        RetentionEvidenceService $evidence,
        string $scope,
        string $action,
        bool $dryRun,
        string $failureCode,
    ): int {
        $batchId = $evidence->recordFailure($scope, $action, $dryRun, $failureCode);
        $this->error(json_encode([
            'batch_id' => $batchId,
            'scope' => $scope,
            'status' => 'failed',
            'failure_code' => $failureCode,
        ], JSON_THROW_ON_ERROR));

        return self::FAILURE;
    }
}
