<?php

namespace App\Services;

use App\Enums\AdminUserStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactAssignmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PermissionChecker $permissionChecker,
    ) {}

    public function assign(User $actor, ContactRequest $contactRequest, ?int $assigneeId): ContactRequest
    {
        return DB::transaction(function () use ($actor, $contactRequest, $assigneeId): ContactRequest {
            $contactRequest = ContactRequest::query()->whereKey($contactRequest->id)->lockForUpdate()->firstOrFail();

            if ($contactRequest->assignee_id === $assigneeId) {
                return $contactRequest;
            }

            if ($assigneeId !== null) {
                $assignee = User::query()->whereKey($assigneeId)->where('status', AdminUserStatus::Active->value)->first();

                if ($assignee === null || ! $this->permissionChecker->allows($assignee, 'contacts.manage')) {
                    throw ValidationException::withMessages([
                        'assignee_id' => ['Ответственным можно назначить только активного сотрудника с правом управления обращениями.'],
                    ]);
                }
            }

            $previousAssigneeId = $contactRequest->assignee_id;
            $contactRequest->update([
                'assignee_id' => $assigneeId,
                'assigned_at' => $assigneeId === null ? null : now(),
            ]);
            $this->auditLogService->record($actor, 'contact.assignee-changed', $contactRequest, [
                'from_assignee_id' => $previousAssigneeId,
                'to_assignee_id' => $assigneeId,
            ]);

            return $contactRequest;
        });
    }
}
