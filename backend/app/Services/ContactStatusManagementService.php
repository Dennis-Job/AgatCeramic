<?php

namespace App\Services;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactStatusManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function update(User $actor, ContactRequest $contactRequest, ContactRequestStatus $targetStatus): ContactRequest
    {
        return DB::transaction(function () use ($actor, $contactRequest, $targetStatus): ContactRequest {
            $contactRequest = ContactRequest::query()->whereKey($contactRequest->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $contactRequest->status;

            if ($currentStatus === $targetStatus) {
                return $contactRequest;
            }

            if ($currentStatus->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => ['Терминальный статус обращения изменить нельзя.'],
                ]);
            }

            if (! in_array($targetStatus, $currentStatus->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => ['Недопустимый переход статуса обращения.'],
                ]);
            }

            $contactRequest->update([
                'status' => $targetStatus,
                'completed_at' => $targetStatus->isTerminal() ? now() : null,
            ]);
            $contactRequest->statusHistory()->create([
                'from_status' => $currentStatus->value,
                'to_status' => $targetStatus->value,
                'actor_id' => $actor->id,
                'actor_snapshot' => ['name' => $actor->name],
                'occurred_at' => now(),
            ]);
            $this->auditLogService->record($actor, 'contact.status-changed', $contactRequest, [
                'from_status' => $currentStatus->value,
                'to_status' => $targetStatus->value,
            ]);

            return $contactRequest;
        });
    }
}
