<?php

namespace App\Services\Retention;

use App\Models\RetentionExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class RetentionEvidenceService
{
    public function __construct(private readonly RetentionPolicy $policy) {}

    public function recordResult(RetentionBatchResult $result): void
    {
        RetentionExecution::query()->create([
            'batch_id' => $result->batchId,
            'policy_version' => $this->policy->version(),
            'scope' => $result->scope,
            'action' => $result->action,
            'mode' => $result->dryRun ? 'dry-run' : 'apply',
            'status' => $result->errors === 0 ? 'completed' : 'failed',
            'cutoff_at' => $result->cutoff,
            'eligible_count' => $result->eligible,
            'processed_count' => $result->processed,
            'hold_count' => $result->held,
            'exception_count' => $result->exceptions,
            'error_count' => $result->errors,
            'failure_code' => $result->errors === 0 ? null : 'batch_error',
            'service_identity' => $this->policy->serviceIdentity(),
            'occurred_at' => now(),
        ]);
    }

    public function recordFailure(string $scope, string $action, bool $dryRun, string $failureCode): string
    {
        $batchId = (string) Str::uuid();
        RetentionExecution::query()->create([
            'batch_id' => $batchId,
            'policy_version' => $this->policy->version(),
            'scope' => $scope,
            'action' => $action,
            'mode' => $dryRun ? 'dry-run' : 'apply',
            'status' => 'failed',
            'error_count' => 1,
            'failure_code' => $failureCode,
            'service_identity' => $this->policy->serviceIdentity(),
            'occurred_at' => now(),
        ]);

        return $batchId;
    }

    public function createTombstone(
        string $batchId,
        string $scope,
        int $recordId,
        string $action,
        ?string $subjectHmac,
    ): void {
        $keyId = $subjectHmac === null ? null : $this->policy->tombstoneKeyId();
        $inserted = DB::table('retention_tombstones')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'batch_id' => $batchId,
            'scope' => $scope,
            'record_id' => $recordId,
            'action' => $action,
            'key_id' => $keyId,
            'subject_hmac' => $subjectHmac,
            'occurred_at' => now(),
        ]);

        if ($inserted === 0) {
            $this->assertExistingTombstoneMatches($scope, $recordId, $action, $keyId, $subjectHmac);
        }
    }

    public function importTombstone(RetentionTombstoneRecord $record): void
    {
        $inserted = DB::table('retention_tombstones')->insertOrIgnore([
            'id' => $record->id,
            'batch_id' => $record->batchId,
            'scope' => $record->scope,
            'record_id' => $record->recordId,
            'action' => $record->action,
            'key_id' => $record->keyId,
            'subject_hmac' => $record->subjectHmac,
            'occurred_at' => $record->occurredAt,
        ]);

        if ($inserted === 0) {
            $this->assertExistingTombstoneMatches(
                $record->scope,
                $record->recordId,
                $record->action,
                $record->keyId,
                $record->subjectHmac,
            );
        }
    }

    /** @param list<string|null> $values */
    public function subjectHmac(string $scope, int $recordId, array $values, ?string $keyId = null): string
    {
        $resolvedKeyId = $keyId ?? $this->policy->tombstoneKeyId();
        $key = $this->policy->tombstoneKeyFor($resolvedKeyId);
        if ($key === null || $key === '') {
            throw new RetentionApplyBlocked('tombstone_key_version_missing');
        }

        $normalized = array_map(
            static fn (?string $value): string => mb_strtolower(trim((string) $value)),
            $values,
        );

        $payload = json_encode([$scope, $recordId, $normalized], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payload, $key);
    }

    private function assertExistingTombstoneMatches(
        string $scope,
        int $recordId,
        string $action,
        ?string $keyId,
        ?string $subjectHmac,
    ): void {
        $existing = DB::table('retention_tombstones')
            ->where('scope', $scope)
            ->where('record_id', $recordId)
            ->where('action', $action)
            ->first(['key_id', 'subject_hmac']);

        $sameKey = $existing !== null && $existing->key_id === $keyId;
        $sameFingerprint = $existing !== null
            && (($existing->subject_hmac === null && $subjectHmac === null)
                || (is_string($existing->subject_hmac) && is_string($subjectHmac)
                    && hash_equals($existing->subject_hmac, $subjectHmac)));

        if (! $sameKey || ! $sameFingerprint) {
            throw new RuntimeException('Retention tombstone identity collision.');
        }
    }
}
