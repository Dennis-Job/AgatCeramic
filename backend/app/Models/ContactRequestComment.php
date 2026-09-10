<?php

namespace App\Models;

use Database\Factories\ContactRequestCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contact_request_id', 'author_id', 'author_snapshot', 'body', 'created_at'])]
class ContactRequestComment extends Model
{
    /** @use HasFactory<ContactRequestCommentFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<ContactRequest, $this> */
    public function contactRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['author_snapshot' => 'array', 'created_at' => 'datetime'];
    }
}
