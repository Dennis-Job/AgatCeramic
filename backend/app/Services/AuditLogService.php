<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AuditLogService
{
    /**
     * Persist a minimal, sanitised description of an important administrative action.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(?User $actor, string $action, ?Model $entity = null, array $metadata = []): AuditLog
    {
        if (! preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)+$/', $action)) {
            throw new InvalidArgumentException('Audit action must use dot notation.');
        }

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'metadata' => $this->sanitizeMetadata($metadata),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $key = (string) $key;
            $sanitized[$key] = $this->isSensitiveKey($key)
                ? '[redacted]'
                : $this->sanitizeValue($value);
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/(?:password|secret|token|email|phone|name|address|passport|inn|snils|card)/i', $key);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeMetadata($value);
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
}
