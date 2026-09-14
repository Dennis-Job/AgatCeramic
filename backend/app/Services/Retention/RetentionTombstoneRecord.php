<?php

namespace App\Services\Retention;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RetentionTombstoneRecord
{
    public function __construct(
        public string $id,
        public string $batchId,
        public string $scope,
        public int $recordId,
        public string $action,
        public ?string $keyId,
        public ?string $subjectHmac,
        public CarbonImmutable $occurredAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $id = self::requiredString($data, 'id');
        $batchId = self::requiredString($data, 'batch_id');
        $scope = self::requiredString($data, 'scope');
        $action = self::requiredString($data, 'action');
        $recordId = filter_var($data['record_id'] ?? null, FILTER_VALIDATE_INT);
        $keyId = self::nullableString($data, 'key_id');
        $subjectHmac = self::nullableString($data, 'subject_hmac');

        if (! Str::isUuid($id) || ! Str::isUuid($batchId)) {
            throw new InvalidArgumentException('Tombstone identifiers must be UUIDs.');
        }

        if (! is_int($recordId) || $recordId <= 0) {
            throw new InvalidArgumentException('Tombstone record_id must be positive.');
        }

        if (! in_array($scope, ['orders', 'contacts'], true)) {
            throw new InvalidArgumentException('Tombstone scope is invalid.');
        }

        $allowedActions = ['order-anonymized', 'order-deleted', 'commercial-row-deleted', 'contact-deleted'];
        if (! in_array($action, $allowedActions, true)) {
            throw new InvalidArgumentException('Tombstone action is invalid.');
        }

        $requiresHmac = in_array($action, ['order-anonymized', 'order-deleted', 'contact-deleted'], true);
        if ($requiresHmac && ($keyId === null || preg_match('/^[a-f0-9]{64}$/', (string) $subjectHmac) !== 1)) {
            throw new InvalidArgumentException('Tombstone keyed fingerprint is invalid.');
        }

        $occurredAt = self::requiredString($data, 'occurred_at');

        return new self($id, $batchId, $scope, $recordId, $action, $keyId, $subjectHmac, CarbonImmutable::parse($occurredAt));
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Tombstone {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Tombstone {$key} is invalid.");
        }

        return $value;
    }
}
