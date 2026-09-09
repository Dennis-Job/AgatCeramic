<?php

namespace App\Models;

use Database\Factories\ContactRequestStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contact_request_id', 'from_status', 'to_status', 'actor_id', 'actor_snapshot', 'occurred_at'])]
class ContactRequestStatusHistory extends Model
{
    /** @use HasFactory<ContactRequestStatusHistoryFactory> */
    use HasFactory;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    /** @return BelongsTo<ContactRequest, $this> */
    public function contactRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['actor_snapshot' => 'array', 'occurred_at' => 'datetime'];
    }
}
