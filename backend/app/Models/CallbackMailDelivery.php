<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['contact_request_id', 'recipient', 'status', 'attempts', 'last_dispatched_at', 'last_attempted_at', 'sent_at'])]
class CallbackMailDelivery extends Model
{
    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['attempts' => 'integer', 'last_dispatched_at' => 'datetime', 'last_attempted_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
