<?php

namespace App\Services;

use App\Models\ContactRequest;
use App\Models\ContactRequestComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContactCommentService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function add(User $author, ContactRequest $contactRequest, string $body): ContactRequestComment
    {
        return DB::transaction(function () use ($author, $contactRequest, $body): ContactRequestComment {
            $contactRequest = ContactRequest::query()->whereKey($contactRequest->id)->lockForUpdate()->firstOrFail();
            $comment = $contactRequest->comments()->create([
                'author_id' => $author->id,
                'author_snapshot' => ['name' => $author->name],
                'body' => $body,
            ]);
            $this->auditLogService->record($author, 'contact.comment-added', $contactRequest);

            return $comment;
        });
    }
}
