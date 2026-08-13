<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

class AuditLogResource extends ApiResource
{
    /** @return array<string, mixed> */
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
            'occurred_at' => $this->occurred_at?->toISOString(),
        ];
    }

    /** @return array{id: int|null, name: string}|null */
    private function actorSnapshot(): ?array
    {
        $snapshot = $this->actor_snapshot ?? [];

        if (isset($snapshot['name'])) {
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
        $snapshot = $this->entity_snapshot ?? [];

        if ($snapshot !== []) {
            return $snapshot;
        }

        return $this->relationLoaded('entity') && $this->entity instanceof User
            ? ['name' => $this->entity->name, 'email' => $this->entity->email]
            : [];
    }

    /** @return list<array{label: string, value: string}> */
    private function details(): array
    {
        $metadata = $this->metadata ?? [];
        $details = [];

        if (array_key_exists('status', $metadata)) {
            $details[] = ['label' => 'Статус', 'value' => $metadata['status'] === 'active' ? 'Активен' : 'Заблокирован'];
        }

        if ($roles = $this->getAttribute('audit_role_names')) {
            $details[] = ['label' => 'Роли', 'value' => implode(', ', $roles)];
        }

        if ($permissions = $this->getAttribute('audit_permission_names')) {
            $details[] = ['label' => 'Права', 'value' => implode(', ', $permissions)];
        }

        if (array_key_exists('slug', $metadata)) {
            $details[] = ['label' => 'Технический код', 'value' => (string) $metadata['slug']];
        }

        if (array_key_exists('affected_records', $metadata)) {
            $details[] = ['label' => 'Затронуто записей', 'value' => (string) $metadata['affected_records']];
        }

        if (array_key_exists('ip', $metadata)) {
            $details[] = ['label' => 'IP-адрес', 'value' => (string) $metadata['ip']];
        }

        return $details;
    }
}
