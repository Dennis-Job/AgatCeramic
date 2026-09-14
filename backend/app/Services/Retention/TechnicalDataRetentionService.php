<?php

namespace App\Services\Retention;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TechnicalDataRetentionService
{
    public function __construct(
        private readonly RetentionPolicy $policy,
        private readonly RetentionEvidenceService $evidence,
    ) {}

    /** @return list<RetentionBatchResult> */
    public function run(bool $dryRun): array
    {
        if (! $dryRun) {
            $this->policy->assertApplyAllowed('technical');
        }

        $sessionLifetime = config('session.lifetime');
        $passwordExpiry = config('auth.passwords.users.expire');

        return [
            $this->processTimestampRows(
                'failed-jobs-deletion',
                'failed_jobs',
                'id',
                $this->policy->failedJobsCutoff(),
                fn (): Builder => DB::table('failed_jobs')->where('failed_at', '<', $this->policy->failedJobsCutoff()),
                $dryRun,
            ),
            $this->processIntegerTimestampRows(
                'expired-sessions-deletion',
                'sessions',
                'id',
                $this->policy->sessionLastActivityCutoff(is_int($sessionLifetime) ? $sessionLifetime : 0),
                $dryRun,
            ),
            $this->processTimestampRows(
                'expired-password-resets-deletion',
                'password_reset_tokens',
                'email',
                $this->policy->passwordResetCutoff(is_int($passwordExpiry) ? $passwordExpiry : 0),
                fn (): Builder => DB::table('password_reset_tokens')
                    ->whereNull('created_at')
                    ->orWhere('created_at', '<=', $this->policy->passwordResetCutoff(is_int($passwordExpiry) ? $passwordExpiry : 0)),
                $dryRun,
            ),
        ];
    }

    /** @param callable(): Builder $queryFactory */
    private function processTimestampRows(
        string $action,
        string $table,
        string $key,
        CarbonImmutable $cutoff,
        callable $queryFactory,
        bool $dryRun,
    ): RetentionBatchResult {
        $batchId = (string) Str::uuid();
        $keys = $queryFactory()->orderBy($key)->limit($this->policy->batchSize())->pluck($key)->all();

        if ($dryRun) {
            $result = new RetentionBatchResult($batchId, 'technical', $action, true, $cutoff, count($keys), 0, 0, 0);
            $this->evidence->recordResult($result);

            return $result;
        }

        return DB::transaction(function () use ($batchId, $action, $table, $key, $cutoff, $queryFactory): RetentionBatchResult {
            $query = $queryFactory()->orderBy($key)->limit($this->policy->batchSize());
            if (DB::getDriverName() === 'pgsql') {
                $query->lock('FOR UPDATE SKIP LOCKED');
            } else {
                $query->lockForUpdate();
            }
            $keys = $query->pluck($key)->all();
            $deleted = $keys === [] ? 0 : DB::table($table)->whereIn($key, $keys)->delete();
            $processed = $deleted;
            $result = new RetentionBatchResult($batchId, 'technical', $action, false, $cutoff, count($keys), $processed, 0, 0);
            $this->evidence->recordResult($result);

            return $result;
        });
    }

    private function processIntegerTimestampRows(
        string $action,
        string $table,
        string $key,
        int $cutoff,
        bool $dryRun,
    ): RetentionBatchResult {
        return $this->processTimestampRows(
            $action,
            $table,
            $key,
            CarbonImmutable::createFromTimestamp($cutoff),
            fn (): Builder => DB::table($table)->where('last_activity', '<=', $cutoff),
            $dryRun,
        );
    }
}
