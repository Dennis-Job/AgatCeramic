<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** @extends ApiResource<AuditLog> */
class AuditLogResource extends ApiResource
{
    /** @return array<string, mixed>|array{} */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor' => $this->actorSnapshot(),
            'entity' => $this->entity_type ? [
                'type' => class_basename($this->entity_type),
                'id' => $this->entity_id,
                'name' => $this->entitySnapshot()['name'] ?? null,
                'email' => $this->entitySnapshot()['email'] ?? null,
            ] : null,
            'metadata' => $this->metadata,
            'details' => $this->details(),
            'occurred_at' => $this->dateValue($this->occurred_at),
        ];
    }

    /** @return array{id: int|null, name: string}|null */
    private function actorSnapshot(): ?array
    {
        $snapshot = $this->actor_snapshot;

        if (is_array($snapshot) && isset($snapshot['name']) && is_string($snapshot['name'])) {
            return ['id' => $this->actor_id, 'name' => $snapshot['name']];
        }

        return $this->relationLoaded('actor') && $this->actor ? [
            'id' => $this->actor->id,
            'name' => $this->actor->name,
        ] : null;
    }

    /** @return array<string, mixed> */
    private function entitySnapshot(): array
    {
        $snapshot = $this->entity_snapshot;

        if (is_array($snapshot)) {
            $normalized = [];
            foreach ($snapshot as $key => $value) {
                if (is_string($key)) {
                    $normalized[$key] = $value;
                }
            }
            if ($normalized !== []) {
                return $normalized;
            }
        }

        $entity = $this->entity;

        return $this->relationLoaded('entity') && $entity instanceof User
            ? ['name' => $entity->getAttribute('name'), 'email' => $entity->getAttribute('email')]
            : [];
    }

    /** @return list<array{label: string, value: string}> */
    private function details(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $details = [];

        if (array_key_exists('status', $metadata)) {
            $details[] = ['label' => 'Статус', 'value' => $metadata['status'] === 'active' ? 'Активен' : 'Заблокирован'];
        }

        $roles = $this->getAttribute('audit_role_names');
        if (is_array($roles) && $roles !== []) {
            $details[] = ['label' => 'Роли', 'value' => implode(', ', array_map($this->stringValue(...), $roles))];
        }

        $permissions = $this->getAttribute('audit_permission_names');
        if (is_array($permissions) && $permissions !== []) {
            $details[] = ['label' => 'Права', 'value' => implode(', ', array_map($this->stringValue(...), $permissions))];
        }

        if (array_key_exists('slug', $metadata)) {
            $details[] = ['label' => 'Технический код', 'value' => $this->stringValue($metadata['slug'])];
        }

        if (array_key_exists('affected_records', $metadata)) {
            $details[] = ['label' => 'Затронуто записей', 'value' => $this->stringValue($metadata['affected_records'])];
        }

        if (array_key_exists('ip', $metadata)) {
            $details[] = ['label' => 'IP-адрес', 'value' => $this->stringValue($metadata['ip'])];
        }

        return $details;
    }

    private function dateValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : (is_int($value) || is_float($value) || is_bool($value) ? (string) $value : '');
    }
}
