<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AuditLogService
{
    public function __construct(private readonly AuditSnapshotService $snapshots) {}

    /** @return array<string, mixed> */
    public function capture(Model $entity): array
    {
        return $this->snapshots->capture($entity);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $metadata
     */
    public function recordUpdated(?User $actor, string $action, Model $entity, array $before, array $metadata = []): ?AuditLog
    {
        $after = $this->capture($entity);
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $after[$field] ?? null];
            }
        }
        if ($changes === [] && empty($metadata['fields'])) {
            return null;
        }

        return $this->record($actor, $action, $entity, [...$metadata, 'changes' => $changes]);
    }

    /**
     * Persist a minimal, sanitised description of an important administrative action.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(?User $actor, string $action, ?Model $entity = null, array $metadata = [], ?AuditLog $initiatedBy = null): AuditLog
    {
        if (! preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)+$/', $action)) {
            throw new InvalidArgumentException('Audit action must use dot notation.');
        }

        if ($entity !== null && preg_match('/\.(?:created|deleted|image-uploaded|image-deleted)$/', $action)) {
            if (str_ends_with($action, '.created')) {
                $entity->refresh();
            }
            $metadata += ['values' => $this->capture($entity)];
        }

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey() ?? $initiatedBy?->actor_id,
            'actor_snapshot' => $actor ? ['name' => $actor->name] : $initiatedBy?->actor_snapshot,
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'entity_snapshot' => $this->snapshots->identity($entity),
            'metadata' => $this->sanitizeMetadata($metadata),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata, bool $businessState = false): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;
            if ($this->isSensitiveKey($key)) {
                $sanitized[$key] = '[redacted]';
            } elseif ($businessState && in_array($key, ['sku', 'barcode', 'article_number', 'product_skus'], true)) {
                $sanitized[$key] = $this->sanitizeIdentifier($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($this->stringKeyedArray($value), $businessState || in_array($key, ['values', 'changes'], true));
            } else {
                $sanitized[$key] = $this->sanitizeValue($value);
            }
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/(?:password|secret|token|email|phone|name|address|passport|inn|snils|card)/i', $key);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if (is_array($value)) {
            return $this->sanitizeMetadata($this->stringKeyedArray($value));
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_string($value)) {
            return preg_replace([
                '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
                '/(?<!\d)\+?\d[\d\s()\-]{8,}\d(?!\d)/',
            ], '[redacted]', $value);
        }

        return is_scalar($value) || $value === null ? $value : '[redacted]';
    }

    private function sanitizeIdentifier(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[a-z0-9_. -]{1,100}$/iD', $value)) {
            return $value;
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = is_int($key) || in_array($key, ['before', 'after'], true)
                    ? $this->sanitizeIdentifier($item)
                    : $this->sanitizeMetadata([(string) $key => $item])[(string) $key];
            }

            return $result;
        }

        return $this->sanitizeValue($value);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
