<?php

namespace App\Services;

use App\Models\ComplianceApproval;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalDocumentManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): LegalDocumentVersion
    {
        return DB::transaction(function () use ($actor, $attributes): LegalDocumentVersion {
            $document = LegalDocumentVersion::query()->create([...$attributes, 'created_by' => $actor->id]);
            $this->auditLogService->record($actor, 'legal-document.created', $document, ['type' => $document->type, 'version' => $document->version]);

            return $document;
        });
    }

    public function publish(User $actor, LegalDocumentVersion $document): LegalDocumentVersion
    {
        return DB::transaction(function () use ($actor, $document): LegalDocumentVersion {
            $document = LegalDocumentVersion::query()->lockForUpdate()->findOrFail($document->id);
            if ($document->published_at !== null) {
                return $document;
            }
            $document->published_at = now();
            $document->save();
            $this->auditLogService->record($actor, 'legal-document.published', $document, ['type' => $document->type, 'version' => $document->version]);

            return $document;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function recordApproval(User $actor, array $attributes): ComplianceApproval
    {
        return DB::transaction(function () use ($actor, $attributes): ComplianceApproval {
            $documentId = filter_var($attributes['document_version_id'] ?? null, FILTER_VALIDATE_INT);
            if (! is_int($documentId)) {
                throw ValidationException::withMessages(['document_version_id' => 'Выберите опубликованную версию политики ПДн.']);
            }
            $document = LegalDocumentVersion::query()->findOrFail($documentId);
            if ($document->type !== 'privacy_policy' || $document->published_at === null) {
                throw ValidationException::withMessages(['document_version_id' => 'Выберите опубликованную версию политики ПДн.']);
            }
            $approval = ComplianceApproval::query()->create([...$attributes, 'recorded_by' => $actor->id]);
            $this->auditLogService->record($actor, 'compliance-approval.recorded', $approval, [
                'role' => $approval->role,
                'decision' => $approval->decision,
                'document_version_id' => $document->id,
            ]);

            return $approval;
        });
    }
}
