<?php

namespace App\Services;

use App\Models\ComplianceApproval;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use App\Support\LegalDocumentContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalDocumentManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LegalDocumentContent $documentContent,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): LegalDocumentVersion
    {
        if (isset($attributes['content'])) {
            $attributes['body'] = $this->documentContent->toPlainText($attributes['content']);
        }

        return DB::transaction(function () use ($actor, $attributes): LegalDocumentVersion {
            $document = LegalDocumentVersion::query()->create([...$attributes, 'created_by' => $actor->id]);
            $this->auditLogService->record($actor, 'legal-document.created', $document, ['type' => $document->type, 'version' => $document->version]);

            return $document;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, LegalDocumentVersion $document, array $attributes): LegalDocumentVersion
    {
        return DB::transaction(function () use ($actor, $document, $attributes): LegalDocumentVersion {
            // Publish takes the same lock: a stale draft cannot overwrite a published version.
            $document = LegalDocumentVersion::query()->lockForUpdate()->findOrFail($document->id);
            if ($document->published_at !== null) {
                throw ValidationException::withMessages(['document' => 'Опубликованную версию нельзя изменить. Создайте новый черновик.']);
            }
            if (isset($attributes['type']) && $attributes['type'] !== $document->type) {
                throw ValidationException::withMessages(['type' => 'Тип сохранённого документа нельзя изменить.']);
            }
            $content = $attributes['content'] ?? null;
            $document->fill([
                'version' => $attributes['version'],
                'body' => $content !== null ? $this->documentContent->toPlainText($content) : $attributes['body'],
                'content' => $content,
            ]);
            $document->save();
            $this->auditLogService->record($actor, 'legal-document.updated', $document, ['type' => $document->type, 'version' => $document->version]);

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
