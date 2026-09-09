<?php

namespace App\Models;

use App\Enums\ContactRequestStatus;
use Database\Factories\ContactRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'phone', 'email', 'message', 'source', 'status', 'assignee_id', 'assigned_at', 'completed_at'])]
class ContactRequest extends Model
{
    /** @use HasFactory<ContactRequestFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return HasMany<ContactRequestStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ContactRequestStatusHistory::class);
    }

    /** @return HasMany<ContactRequestComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ContactRequestComment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ContactRequestStatus::class,
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
